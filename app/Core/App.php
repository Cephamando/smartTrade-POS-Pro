<?php

namespace App\Core;

use App\Models\Setting;

final class App
{
    private array $settings = [];

    public function run(): void
    {
        $this->sendSecurityHeaders();
        $this->bootSession();
        $this->loadSettings();
        $this->handleLocationSwitch();
        if ($this->handleJsonAction()) {
            return;
        }

        $page = $this->resolvePage();
        $page = $this->enforceAccess($page);
        (new Router($this))->dispatch($page);
    }

    public function settings(): array
    {
        return $this->settings;
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('Permissions-Policy: geolocation=(), microphone=*, camera=*');
        header(
            "Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "img-src 'self' data: https://api.qrserver.com; font-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "frame-src 'self' http: https:; child-src 'self' http: https:;"
        );
    }

    private function bootSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function loadSettings(): void
    {
        $this->settings = Setting::all();
        define('LICENSE_TIER', strtolower($this->settings['license_tier'] ?? 'lite'));
        define('APP_NAME', $this->settings['business_name'] ?? 'OdeliaPOS');
        define('THEME_COLOR', $this->settings['theme_color'] ?? '#3e2723');
        define('RECEIPT_HEADER', $this->settings['receipt_header'] ?? '');
        define('RECEIPT_FOOTER', $this->settings['receipt_footer'] ?? 'Thank you!');
        define('LOCKOUT_DATE', $this->settings['lockout_date'] ?? '');
        define('SYSTEM_LOCKED', (!empty(LOCKOUT_DATE) && strtotime(LOCKOUT_DATE) <= time()));
    }

    private function handleLocationSwitch(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['global_switch_location'])) {
            return;
        }
        if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dev'], true)) {
            $newLocId = (int)$_POST['target_location_id'];
            $_SESSION['location_id'] = $newLocId;
            $_SESSION['pos_location_id'] = $newLocId;
            $_SESSION['manual_location_override'] = true;
            $stmt = Database::connection()->prepare('SELECT name FROM locations WHERE id = ?');
            $stmt->execute([$newLocId]);
            $_SESSION['location_name'] = $stmt->fetchColumn() ?: 'Unknown';
        }
        $page = $_GET['page'] ?? 'dashboard';
        header('Location: index.php?page=' . urlencode((string)$page));
        exit;
    }

    private function handleJsonAction(): bool
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        $action = (string)$_GET['action'];

        if ($action === 'logout') {
            session_destroy();
            header('Location: index.php?page=login');
            exit;
        }

        // ALLOW VSDC & OTHER ACTIONS TO PASS THROUGH TO ROUTER
        if (!in_array($action, ['check_ready_orders', 'get_shift_sales', 'get_stock_level'], true)) {
            return false;
        }

        $pdo = Database::connection();
        header('Content-Type: application/json');

        if ($action === 'check_ready_orders') {
            $locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
            session_write_close(); // Prevent session lock freezes
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.location_id = ? AND si.status = 'ready' AND si.fulfillment_status = 'uncollected'");
                $stmt->execute([$locationId]);
                echo json_encode(['count' => (int)$stmt->fetchColumn()]);
            } catch (\Throwable $e) {
                echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
            }
            return true;
        }

        if ($action === 'get_shift_sales') {
            $shiftId = (int)($_GET['shift_id'] ?? 0);
            session_write_close();
            try {
                $stmt = $pdo->prepare("SELECT id, created_at, customer_name, final_total, payment_method FROM sales WHERE shift_id = ? AND payment_status = 'paid' ORDER BY created_at DESC");
                $stmt->execute([$shiftId]);
                echo json_encode($stmt->fetchAll());
            } catch (\Throwable $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            return true;
        }

        if ($action === 'get_stock_level') {
            $productId = $_GET['product_id'] ?? 0;
            $locationId = $_GET['location_id'] ?? 0;
            session_write_close();
            try {
                $stmt = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
                $stmt->execute([$productId, $locationId]);
                echo json_encode(['stock' => (int)$stmt->fetchColumn() ?: 0]);
            } catch (\Throwable $e) {
                echo json_encode(['stock' => 0, 'error' => $e->getMessage()]);
            }
            return true;
        }

        return false;
    }

    private function resolvePage(): string
    {
        $page = isset($_GET['page']) ? basename((string)$_GET['page']) : '';
        $userRole = $_SESSION['role'] ?? 'cashier';

        if ($page !== '') {
            return $page;
        }

        if (isset($_SESSION['user_id'])) {
            if (in_array($userRole, ['admin', 'manager', 'dev'], true)) {
                return 'dashboard';
            }
            if (in_array($userRole, ['chef', 'head_chef'], true) && in_array(LICENSE_TIER, ['pro+', 'enterprise'], true)) {
                return 'kitchen';
            }
            return 'pos';
        }

        return 'login';
    }

    private function enforceAccess(string $page): string
    {
        $routes = require APP_PATH . '/Config/routes.php';
        $userRole = $_SESSION['role'] ?? 'cashier';
        $needsAuth = ($routes[$page]['auth'] ?? true) !== false;

        if ($needsAuth && !isset($_SESSION['user_id'])) {
            session_destroy();
            header('Location: index.php?page=login');
            exit;
        }

        if ($page === 'login' && isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }

        if (isset($_SESSION['user_id']) && !in_array($userRole, ['admin', 'manager', 'dev'], true)) {
            $allowedCommon = ['receipt', 'logout', 'pickup', 'print_shift', 'end_shift_action'];
            if (in_array($userRole, ['chef', 'head_chef'], true) && in_array(LICENSE_TIER, ['pro+', 'enterprise'], true)) {
                $allowed = array_merge($allowedCommon, ['kitchen', 'kds', 'menu', 'inventory']);
                if (!in_array($page, $allowed, true)) {
                    $page = 'kitchen';
                }
            } else {
                $allowed = array_merge($allowedCommon, ['pos', 'transfers']);
                if (!in_array($page, $allowed, true)) {
                    $page = 'pos';
                }
            }
        }

        if (isset($_SESSION['user_id'])) {
            $proPlusFeatures = ['kds', 'kitchen', 'menu', 'tables'];
            $proFeatures = ['members', 'audit', 'receive_stock', 'receiving_bay', 'purchasing', 'transfers', 'pickup'];

            if (in_array($page, $proPlusFeatures, true) && !in_array(LICENSE_TIER, ['pro+', 'enterprise'], true)) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = 'This feature requires Pro+ or Enterprise.';
                $page = in_array($userRole, ['admin', 'manager', 'dev'], true) ? 'dashboard' : 'pos';
            }

            if (in_array($page, $proFeatures, true) && LICENSE_TIER === 'lite') {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = 'This feature requires Pro or higher.';
                $page = in_array($userRole, ['admin', 'manager', 'dev'], true) ? 'dashboard' : 'pos';
            }
        }

        if (SYSTEM_LOCKED && isset($_SESSION['role']) && $_SESSION['role'] !== 'dev') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'login') {
                die("<div style='padding:20px; font-family:sans-serif;'><h2>License expired</h2><p>Changes are disabled until the license is renewed.</p><a href='index.php'>Return</a></div>");
            }
            if (!in_array($userRole, ['admin', 'manager'], true)) {
                if ($page !== 'logout') {
                    die("<div style='text-align:center; padding-top:150px; font-family:sans-serif; background:#111; color:#fff; height:100vh; margin:0;'><h1 style='color:#ff4d4d; font-size: 3rem;'>License expired</h1><p style='font-size: 1.2rem; color: #ccc;'>Notify management to restore access.</p><br><br><a href='index.php?action=logout' style='color:#fff; background: #dc3545; border-radius: 5px; padding:15px 30px; text-decoration:none; font-weight: bold;'>Sign out</a></div>");
                }
            } else {
                $allowedWhenLocked = ['reports', 'z_read', 'logout', 'receipt', 'print_shift'];
                if (!in_array($page, $allowedWhenLocked, true)) {
                    $page = 'reports';
                }
            }
        }

        return $page;
    }
}