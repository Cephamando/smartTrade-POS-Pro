<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Sale;

final class ReportsController extends Controller
{
    public function index(): void
    {
        if (isset($_POST['fetch_sale_details'])) {
            try {
                $this->jsonExit(['status' => 'success', 'items' => Sale::saleItemsForModal((int)$_POST['sale_id'])]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }
        
        // Feature 2: Tax Drilldown AJAX endpoint
        if (isset($_POST['fetch_tax_details'])) {
            try {
                $start = $_POST['start'];
                $end = $_POST['end'];
                $locId = $_POST['location'] ?? '';
                $empId = (int)($_POST['employee_id'] ?? 0);
                
                $sales = Report::salesRows($start, $end, $locId);
                
                if ($empId > 0) {
                    $empStmt = $this->pdo()->prepare("SELECT username FROM users WHERE id = ?");
                    $empStmt->execute([$empId]);
                    $empUsername = $empStmt->fetchColumn();
                    if ($empUsername) {
                        $filtered = [];
                        foreach ($sales as $row) {
                            $rowUser = $row['cashier'] ?? $row['username'] ?? '';
                            if (strcasecmp($rowUser, $empUsername) === 0) {
                                $filtered[] = $row;
                            }
                        }
                        $sales = $filtered;
                    }
                }
                
                $data = [];
                foreach($sales as $s) {
                    if (strtolower($s['payment_status'] ?? '') === 'paid' && $s['final_total'] > 0) {
                        $net = $s['final_total'] / 1.16; // Reverse calc 16% VAT
                        $tax = $s['final_total'] - $net;
                        $data[] = [
                            'id' => $s['id'],
                            'date' => date('M d, Y H:i', strtotime($s['created_at'])),
                            'customer' => $s['customer_name'] ?? 'Walk-in',
                            'taxable' => number_format($net, 2),
                            'tax' => number_format($tax, 2)
                        ];
                    }
                }
                
                $this->jsonExit(['status' => 'success', 'data' => $data]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_sale'])) {
            if (!$this->isManager()) {
                $this->flash('error', 'You do not have permission to process refunds.');
                $this->redirect('reports');
            }
            try {
                $result = (new \App\Services\Refund($this->pdo()))->refundAll(
                    (int)$_POST['sale_id'],
                    $this->userId(),
                    'Full refund',
                    (string)($_POST['rfd_rsn_cd'] ?? '07')
                );
                $msg = 'Refund processed and stock restored.';
                if (!empty($result['zra_msg'])) {
                    $msg .= ' ' . $result['zra_msg'];
                }
                $this->flash('success', $msg);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to process the refund.');
            }
            $this->redirect('reports');
        }

        $startDate = $_GET['start'] ?? date('Y-m-d');
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $locationId = $_GET['location'] ?? '';
        $employeeId = $_GET['employee_id'] ?? '';
        $reportType = $_GET['type'] ?? 'sales';
        if (!in_array($reportType, ['sales', 'items', 'categories', 'taxes', 'tips'], true)) {
            $reportType = 'sales';
        }

        $employees = $this->pdo()->query("SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY username ASC")->fetchAll();

        $reportData = [];
        if ($reportType === 'sales' || $reportType === 'taxes') {
            $reportData = Report::salesRows($startDate, $endDate, $locationId);
            
            // Apply Employee Filter dynamically for sales tickets
            if ($employeeId !== '' && $employeeId > 0) {
                $empUsername = '';
                foreach ($employees as $e) {
                    if ((string)$e['id'] === (string)$employeeId) {
                        $empUsername = $e['username'];
                        break;
                    }
                }
                
                if ($empUsername !== '') {
                    $filtered = [];
                    foreach ($reportData as $row) {
                        $rowUser = $row['cashier'] ?? $row['username'] ?? '';
                        if (strcasecmp($rowUser, $empUsername) === 0) {
                            $filtered[] = $row;
                        }
                    }
                    $reportData = $filtered;
                }
            }
        } elseif ($reportType === 'items') {
            // Aggregated Items (Products) with direct SQL so we can reliably filter by Employee
            $sql = "SELECT p.name, p.sku, SUM(si.quantity) as qty_sold, SUM(si.price * si.quantity) as revenue
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    JOIN products p ON si.product_id = p.id
                    WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ? 
                    AND s.payment_status = 'paid'";
            $params = [$startDate, $endDate];
            if ($locationId !== '') { $sql .= " AND s.location_id = ?"; $params[] = $locationId; }
            if ($employeeId !== '' && $employeeId > 0) { $sql .= " AND s.user_id = ?"; $params[] = $employeeId; }
            $sql .= " GROUP BY p.id, p.name, p.sku ORDER BY revenue DESC";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
        } elseif ($reportType === 'categories') {
            // Aggregated Categories with direct SQL
            $sql = "SELECT c.name as category_name, SUM(si.quantity) as qty_sold, SUM(si.price * si.quantity) as revenue
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    JOIN products p ON si.product_id = p.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ? 
                    AND s.payment_status = 'paid'";
            $params = [$startDate, $endDate];
            if ($locationId !== '') { $sql .= " AND s.location_id = ?"; $params[] = $locationId; }
            if ($employeeId !== '' && $employeeId > 0) { $sql .= " AND s.user_id = ?"; $params[] = $employeeId; }
            $sql .= " GROUP BY c.id, c.name ORDER BY revenue DESC";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        }
        
        $tipsReport = null;
        if ($reportType === 'tips') {
            $tipsReport = Report::tipsAndSurcharges($startDate, $endDate, $locationId);
            $reportData = $tipsReport['staff'];
        }

        $metrics = Report::metrics($startDate, $endDate, $locationId);
        $refundStats = Report::refundStats($startDate, $endDate, $locationId);
        $voidStats = Report::voidStats($startDate, $endDate, $locationId);

        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationId' => $locationId,
            'employeeId' => $employeeId,
            'reportType' => $reportType,
            'metrics' => $metrics,
            'refundStats' => $refundStats,
            'voidStats' => $voidStats,
            'reportData' => $reportData,
            'tipsReport' => $tipsReport,
            'locations' => Location::all(),
            'employees' => $employees,
        ]);
    }
}
