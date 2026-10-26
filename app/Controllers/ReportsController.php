<?php

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Report;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\OwnerOverview;
use App\Services\ReportNarrator;

final class ReportsController extends Controller
{
    public function index(): void
    {
        if (isset($_POST['explain_report'])) {
            if (!in_array($this->role(), ['admin', 'manager', 'dev', 'owner'], true)) {
                $this->jsonExit(['status' => 'error', 'msg' => 'You do not have permission to explain these figures.']);
            }
            try {
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->reportBrief($_POST));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
        }

        if (isset($_POST['figure_drill'])) {
            $this->requireRole(['admin', 'manager', 'dev', 'owner']);
            try {
                $this->jsonExit((new OwnerOverview($this->pdo()))->drill($_POST));
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() !== '' ? $e->getMessage() : 'The detail could not be opened.']);
            }
        }

        if (isset($_POST['fetch_sale_details'])) {
            try {
                $saleId = (int)$_POST['sale_id'];
                $gateway = \App\Services\Flutterwave::detailsForSales($this->pdo(), [$saleId]);
                $this->jsonExit([
                    'status' => 'success',
                    'items' => Sale::saleItemsForModal($saleId),
                    'gateway' => $gateway[$saleId] ?? null,
                ]);
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

        if (isset($_POST['fetch_statutory_details'])) {
            try {
                $key = (string)($_POST['key'] ?? '');
                $lines = Report::statutoryLines(
                    (string)($_POST['start'] ?? ''),
                    (string)($_POST['end'] ?? ''),
                    $_POST['location'] ?? '',
                    (int)($_POST['employee_id'] ?? 0),
                    $key
                );
                $split = $key !== 'paye';
                $data = [];
                foreach ($lines as $line) {
                    if ($key === 'napsa') {
                        $employee = (float)($line['napsa_employee'] ?? 0);
                        $employer = (float)($line['napsa_employer'] ?? 0);
                    } elseif ($key === 'nhima') {
                        $employee = (float)($line['nhima_employee'] ?? 0);
                        $employer = (float)($line['nhima_employer'] ?? 0);
                    } else {
                        $employee = (float)($line['paye'] ?? 0);
                        $employer = 0.0;
                    }
                    $data[] = [
                        'slip_id' => (int)$line['slip_id'],
                        'run_id' => (int)$line['run_id'],
                        'name' => (string)$line['full_name'],
                        'run' => (string)$line['title'],
                        'date' => $line['pay_date'] ? date('d M Y', strtotime((string)$line['pay_date'])) : '',
                        'employee' => number_format($employee, 2),
                        'employer' => number_format($employer, 2),
                        'amount' => number_format($employee + $employer, 2),
                    ];
                }
                $this->jsonExit(['status' => 'success', 'split' => $split, 'data' => $data]);
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
        $daypart = (string)($_GET['daypart'] ?? '');
        if (!in_array($daypart, ['morning', 'afternoon', 'evening', 'night'], true)) {
            $daypart = '';
        }
        $hourSql = \App\Models\Report::daypartClause($daypart);
        $reportType = $_GET['type'] ?? 'sales';
        if (!in_array($reportType, ['sales', 'items', 'categories', 'taxes', 'tips', 'finance'], true)) {
            $reportType = 'sales';
        }

        $employees = $this->pdo()->query("SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY username ASC")->fetchAll();

        $reportData = [];
        if ($reportType === 'sales' || $reportType === 'taxes') {
            $reportData = Report::salesRows($startDate, $endDate, $locationId, $daypart);
            
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
            if ($reportType === 'sales') {
                $reportData = \App\Services\Flutterwave::attach($this->pdo(), $reportData);
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
            $sql .= $hourSql;
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
            $sql .= $hourSql;
            $sql .= " GROUP BY c.id, c.name ORDER BY revenue DESC";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        }
        
        $tipsReport = null;
        $finance = null;
        if ($reportType === 'tips') {
            $tipsReport = Report::tipsAndSurcharges($startDate, $endDate, $locationId, $daypart);
            $reportData = $tipsReport['staff'];
        } elseif ($reportType === 'finance') {
            $finance = Report::financial($startDate, $endDate, $locationId, (int)$employeeId);
            $reportData = $finance['rows'];
        }

        $statutory = $reportType === 'taxes'
            ? Report::statutory($startDate, $endDate, $locationId, (int)$employeeId)
            : null;

        $tradeFilters = OwnerOverview::filters([
            'start' => $startDate,
            'end' => $endDate,
            'location' => $locationId,
            'employee_id' => $employeeId,
            'daypart' => $daypart,
        ]);
        $tradeFigures = (new OwnerOverview($this->pdo()))->figures($tradeFilters);
        $businessValue = Asset::snapshot($tradeFilters['location']);

        $metrics = Report::metrics($startDate, $endDate, $locationId, $daypart);
        $refundStats = Report::refundStats($startDate, $endDate, $locationId, $daypart);
        $voidStats = Report::voidStats($startDate, $endDate, $locationId, $daypart);

        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationId' => $locationId,
            'employeeId' => $employeeId,
            'daypart' => $daypart,
            'reportType' => $reportType,
            'metrics' => $metrics,
            'refundStats' => $refundStats,
            'voidStats' => $voidStats,
            'reportData' => $reportData,
            'tipsReport' => $tipsReport,
            'finance' => $finance,
            'statutory' => $statutory,
            'locations' => Location::all(),
            'employees' => $employees,
            'explainReady' => Guide::ready(Setting::all()),
            'tradeFigures' => $tradeFigures,
            'businessValue' => $businessValue,
            'figureFilters' => $tradeFilters,
        ]);
    }
}
