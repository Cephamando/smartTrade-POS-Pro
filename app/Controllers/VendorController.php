<?php

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Vendor;

final class VendorController extends Controller
{
    private const IDLE_SECONDS = 28800;

    public function index(): void
    {
        Vendor::ensurePortal();
        Purchase::ensureSchema();
        $this->touch();

        if (isset($_POST['vendor_logout'])) {
            $this->clearPortal();
            $this->redirect('vendor');
        }

        if (isset($_POST['vendor_login'])) {
            $row = Vendor::authenticate((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            if ($row === null) {
                $this->flash('error', 'Those portal details were not accepted.');
            } else {
                session_regenerate_id(true);
                $_SESSION['vendor_portal_id'] = $row['id'];
                $_SESSION['vendor_portal_seen'] = time();
            }
            $this->redirect('vendor');
        }

        $vendor = $this->portalVendor();
        if ($vendor === null) {
            $this->render([
                'screen' => 'login',
                'vendor' => null,
                'flash' => $this->takeFlash(),
            ]);
            return;
        }

        $vendorId = (int)$vendor['id'];
        try {
            if (isset($_POST['submit_quote'])) {
                Purchase::submitQuotation(
                    $vendorId,
                    (int)($_POST['inquiry_id'] ?? 0),
                    $this->dateOrNull((string)($_POST['delivery_date'] ?? '')),
                    $this->dateOrNull((string)($_POST['valid_until'] ?? '')),
                    trim((string)($_POST['notes'] ?? '')),
                    (array)($_POST['lines'] ?? [])
                );
                $this->flash('success', 'Quotation sent.');
                $this->redirect('vendor', ['view' => 'inquiries']);
            }
            if (isset($_POST['confirm_order'])) {
                Purchase::setVendorResponse((int)$_POST['order_id'], $vendorId, 'confirm');
                $this->flash('success', 'Order confirmed.');
                $this->redirect('vendor', ['view' => 'order', 'order_id' => (int)$_POST['order_id']]);
            }
            if (isset($_POST['decline_order'])) {
                Purchase::setVendorResponse((int)$_POST['order_id'], $vendorId, 'decline');
                $this->flash('success', 'Order declined.');
                $this->redirect('vendor', ['view' => 'orders']);
            }
            if (isset($_POST['dispatch_order'])) {
                Purchase::setVendorResponse((int)$_POST['order_id'], $vendorId, 'dispatch', (string)($_POST['delivery_ref'] ?? ''));
                $this->flash('success', 'Order marked dispatched.');
                $this->redirect('vendor', ['view' => 'order', 'order_id' => (int)$_POST['order_id']]);
            }
            if (isset($_POST['save_offering'])) {
                Vendor::saveOffering($vendorId, (int)($_POST['product_id'] ?? 0), (float)($_POST['unit_cost'] ?? 0));
                $this->flash('success', 'Price saved.');
                $this->redirect('vendor', ['view' => 'products']);
            }
            if (isset($_POST['remove_offering'])) {
                Vendor::removeOffering($vendorId, (int)($_POST['product_id'] ?? 0));
                $this->flash('success', 'Price removed.');
                $this->redirect('vendor', ['view' => 'products']);
            }
            if (isset($_POST['save_profile'])) {
                Vendor::updatePortalProfile(
                    $vendorId,
                    (string)($_POST['contact_person'] ?? ''),
                    (string)($_POST['phone'] ?? ''),
                    (string)($_POST['new_password'] ?? '')
                );
                $this->flash('success', 'Account saved.');
                $this->redirect('vendor', ['view' => 'account']);
            }
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'That could not be saved.');
        }

        $vendor = $this->portalVendor() ?? $vendor;
        $view = (string)($_GET['view'] ?? 'home');
        if (!in_array($view, ['home', 'products', 'inquiries', 'inquiry', 'orders', 'order', 'account'], true)) {
            $view = 'home';
        }
        $inquiry = $view === 'inquiry' ? Purchase::vendorInquiry($vendorId, (int)($_GET['inquiry_id'] ?? 0)) : null;
        $order = $view === 'order' ? Purchase::vendorOrder($vendorId, (int)($_GET['order_id'] ?? 0)) : null;

        $this->render([
            'screen' => $view,
            'vendor' => $vendor,
            'home' => Purchase::vendorHome($vendorId),
            'inquiries' => Purchase::vendorInquiries($vendorId),
            'inquiry' => $inquiry,
            'orders' => Purchase::vendorOrders($vendorId),
            'order' => $order,
            'offerings' => $view === 'products' ? Vendor::offerings($vendorId) : [],
            'stockItems' => $view === 'products' ? Vendor::stockItems() : [],
            'flash' => $this->takeFlash(),
        ]);
    }

    private function portalVendor(): ?array
    {
        $id = (int)($_SESSION['vendor_portal_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $vendor = Vendor::find($id);
        if (!$vendor || empty($vendor['portal_active']) || (int)($vendor['is_active'] ?? 1) !== 1) {
            $this->clearPortal();
            return null;
        }
        return $vendor;
    }

    private function touch(): void
    {
        if (empty($_SESSION['vendor_portal_id'])) {
            return;
        }
        $seen = (int)($_SESSION['vendor_portal_seen'] ?? 0);
        if ($seen > 0 && time() - $seen > self::IDLE_SECONDS) {
            $this->clearPortal();
            $this->flash('error', 'Sign in again.');
            return;
        }
        $_SESSION['vendor_portal_seen'] = time();
    }

    private function clearPortal(): void
    {
        unset($_SESSION['vendor_portal_id'], $_SESSION['vendor_portal_seen']);
    }

    /** @return array{type: string, msg: string} */
    private function takeFlash(): array
    {
        $flash = [
            'type' => (string)($_SESSION['swal_type'] ?? ''),
            'msg' => (string)($_SESSION['swal_msg'] ?? ''),
        ];
        unset($_SESSION['swal_type'], $_SESSION['swal_msg']);
        return $flash;
    }

    private function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
