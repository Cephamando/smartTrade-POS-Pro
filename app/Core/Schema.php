<?php

namespace App\Core;

use PDO;

/**
 * Idempotent schema upgrades that run once per code version.
 *
 * The app is deployed with `git pull`, so there is no migration CLI step.
 * Bump VERSION whenever a new statement is added; the upgrade runs on the
 * next request and the version is stored in settings.schema_version.
 */
final class Schema
{
    public const VERSION = 3;

    public static function ensure(PDO $pdo, array $settings): void
    {
        $current = (int)($settings['schema_version'] ?? 0);
        if ($current >= self::VERSION) {
            return;
        }
        try {
            self::upgrade($pdo);
            $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?'
            )->execute(['schema_version', (string)self::VERSION, (string)self::VERSION]);
        } catch (\Throwable $e) {
            error_log('Schema upgrade failed: ' . $e->getMessage());
        }
    }

    public static function upgrade(PDO $pdo): void
    {
        // --- Sales: surcharge, foreign tender, gateway reference, portal fields
        self::addColumn($pdo, 'sales', 'surcharge_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        self::addColumn($pdo, 'sales', 'surcharge_reason', 'VARCHAR(120) NULL');
        self::addColumn($pdo, 'sales', 'tender_currency', 'VARCHAR(3) NULL');
        self::addColumn($pdo, 'sales', 'tender_fx_rate', 'DECIMAL(16,6) NULL');
        self::addColumn($pdo, 'sales', 'tender_fx_amount', 'DECIMAL(12,2) NULL');
        self::addColumn($pdo, 'sales', 'gateway_ref', 'VARCHAR(100) NULL');
        self::addColumn($pdo, 'sales', 'order_source', "VARCHAR(20) NOT NULL DEFAULT 'pos'");
        self::addColumn($pdo, 'sales', 'order_type', 'VARCHAR(20) NULL');
        self::addColumn($pdo, 'sales', 'order_notes', 'VARCHAR(500) NULL');
        self::addColumn($pdo, 'sales', 'customer_id', 'INT NULL');
        self::addColumn($pdo, 'sales', 'customer_phone', 'VARCHAR(30) NULL');

        // --- Post-payment tips (never alter the fiscalised sale)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sale_tips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                shift_id INT NULL,
                location_id INT NOT NULL,
                staff_user_id INT NULL,
                recorded_by INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
                note VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sale (sale_id),
                KEY idx_shift (shift_id),
                KEY idx_staff (staff_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // --- Surcharge reasons managed by admin
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS surcharge_reasons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                default_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                default_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        if ((int)$pdo->query('SELECT COUNT(*) FROM surcharge_reasons')->fetchColumn() === 0) {
            $pdo->exec(
                "INSERT INTO surcharge_reasons (name, default_amount, default_percent, sort_order) VALUES
                 ('Take-away packaging', 10.00, 0, 1),
                 ('Delivery', 25.00, 0, 2),
                 ('Late-night service', 0, 10.00, 3),
                 ('Corkage', 50.00, 0, 4)"
            );
        }

        // --- Goods received: units / packs / line totals
        foreach (['grv_items', 'purchase_order_items'] as $t) {
            self::addColumn($pdo, $t, 'unit', "VARCHAR(20) NULL");
            self::addColumn($pdo, $t, 'pack_size', 'DECIMAL(12,4) NOT NULL DEFAULT 1');
            self::addColumn($pdo, $t, 'pack_qty', 'DECIMAL(12,4) NULL');
            self::addColumn($pdo, $t, 'pack_cost', 'DECIMAL(12,4) NULL');
            self::addColumn($pdo, $t, 'line_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
        self::addColumn($pdo, 'grvs', 'subtotal', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        self::addColumn($pdo, 'grvs', 'tax_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        self::addColumn($pdo, 'grvs', 'notes', 'VARCHAR(500) NULL');

        // --- Payment gateway transactions (Flutterwave)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payment_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(30) NOT NULL DEFAULT 'flutterwave',
                tx_ref VARCHAR(80) NOT NULL,
                provider_tx_id VARCHAR(80) NULL,
                flw_ref VARCHAR(100) NULL,
                sale_id INT NULL,
                location_id INT NULL,
                user_id INT NULL,
                customer_id INT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'ZMW',
                charged_amount DECIMAL(12,2) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                payment_type VARCHAR(40) NULL,
                customer_phone VARCHAR(30) NULL,
                customer_email VARCHAR(120) NULL,
                customer_name VARCHAR(120) NULL,
                checkout_url VARCHAR(500) NULL,
                raw_response MEDIUMTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                verified_at DATETIME NULL,
                used_at DATETIME NULL,
                UNIQUE KEY uq_tx_ref (tx_ref),
                KEY idx_sale (sale_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // --- FX rates cache
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS fx_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                base_code VARCHAR(3) NOT NULL,
                quote_code VARCHAR(3) NOT NULL,
                rate DECIMAL(18,8) NOT NULL,
                source VARCHAR(40) NOT NULL DEFAULT 'api',
                fetched_at DATETIME NOT NULL,
                UNIQUE KEY uq_pair (base_code, quote_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // --- Payroll
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                employee_no VARCHAR(30) NULL,
                full_name VARCHAR(120) NOT NULL,
                nrc VARCHAR(30) NULL,
                tpin VARCHAR(30) NULL,
                napsa_no VARCHAR(30) NULL,
                nhima_no VARCHAR(30) NULL,
                phone VARCHAR(30) NULL,
                email VARCHAR(120) NULL,
                position VARCHAR(80) NULL,
                department VARCHAR(80) NULL,
                location_id INT NULL,
                hire_date DATE NULL,
                termination_date DATE NULL,
                pay_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
                basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
                hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
                pay_method VARCHAR(20) NOT NULL DEFAULT 'bank',
                bank_name VARCHAR(80) NULL,
                bank_account VARCHAR(50) NULL,
                mobile_money VARCHAR(30) NULL,
                napsa_exempt TINYINT(1) NOT NULL DEFAULT 0,
                nhima_exempt TINYINT(1) NOT NULL DEFAULT 0,
                paye_exempt TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payroll_components (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                kind VARCHAR(10) NOT NULL DEFAULT 'earning',
                calc VARCHAR(10) NOT NULL DEFAULT 'fixed',
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                is_taxable TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        if ((int)$pdo->query('SELECT COUNT(*) FROM payroll_components')->fetchColumn() === 0) {
            $pdo->exec(
                "INSERT INTO payroll_components (name, kind, calc, amount, is_taxable, sort_order) VALUES
                 ('Housing allowance', 'earning', 'percent', 0, 1, 1),
                 ('Transport allowance', 'earning', 'fixed', 0, 1, 2),
                 ('Lunch allowance', 'earning', 'fixed', 0, 1, 3),
                 ('Overtime', 'earning', 'fixed', 0, 1, 4),
                 ('Commission', 'earning', 'fixed', 0, 1, 5),
                 ('Salary advance', 'deduction', 'fixed', 0, 0, 10),
                 ('Loan repayment', 'deduction', 'fixed', 0, 0, 11),
                 ('Till shortage', 'deduction', 'fixed', 0, 0, 12)"
            );
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS employee_components (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                component_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                UNIQUE KEY uq_emp_comp (employee_id, component_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payroll_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(120) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                pay_date DATE NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'draft',
                created_by INT NOT NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                paid_at DATETIME NULL,
                total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_paye DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_napsa DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_nhima DECIMAL(14,2) NOT NULL DEFAULT 0,
                notes VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payslips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                run_id INT NOT NULL,
                employee_id INT NOT NULL,
                days_worked DECIMAL(6,2) NULL,
                hours_worked DECIMAL(8,2) NULL,
                basic_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
                allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
                tips DECIMAL(12,2) NOT NULL DEFAULT 0,
                gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
                taxable_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
                paye DECIMAL(12,2) NOT NULL DEFAULT 0,
                napsa_employee DECIMAL(12,2) NOT NULL DEFAULT 0,
                napsa_employer DECIMAL(12,2) NOT NULL DEFAULT 0,
                nhima_employee DECIMAL(12,2) NOT NULL DEFAULT 0,
                nhima_employer DECIMAL(12,2) NOT NULL DEFAULT 0,
                other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
                breakdown TEXT NULL,
                paid_at DATETIME NULL,
                UNIQUE KEY uq_run_emp (run_id, employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // --- Customer portal
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS customers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                email VARCHAR(120) NULL,
                password_hash VARCHAR(255) NOT NULL,
                address VARCHAR(255) NULL,
                member_id INT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_phone (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}
