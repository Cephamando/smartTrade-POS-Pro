<?php

namespace App\Controllers;

use App\Models\Location;

final class LocationsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'dev']);
        Location::ensureSchema();
        $this->ensurePendingColumn();

        $userRole = $_SESSION['role'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['add_location'])) {
                    $this->verifyDevAuth($userRole);
                    $name = trim((string)$_POST['name']);
                    if ($name !== '') {
                        Location::create(
                            $name, 
                            (string)$_POST['type'], 
                            trim((string)$_POST['address']),
                            isset($_POST['can_sell']) ? 1 : 0,
                            isset($_POST['can_receive']) ? 1 : 0
                        );
                        $this->flash('success', "Location '$name' created.");
                    }
                } elseif (isset($_POST['edit_location'])) {
                    $id = (int)$_POST['location_id'];
                    $name = trim((string)$_POST['name']);
                    
                    if ($userRole === 'dev') {
                        // Developer edits apply immediately
                        if ($name !== '') {
                            Location::update(
                                $id,
                                $name,
                                (string)$_POST['type'], 
                                trim((string)$_POST['address']),
                                isset($_POST['can_sell']) ? 1 : 0,
                                isset($_POST['can_receive']) ? 1 : 0
                            );
                            // Clear any stale pending requests
                            $this->pdo()->prepare("UPDATE locations SET pending_edits = NULL WHERE id = ?")->execute([$id]);
                            $this->flash('success', "Location '$name' updated.");
                        }
                    } else {
                        // Admin edits are parked in the approval queue
                        $pendingData = [
                            'name' => $name,
                            'type' => (string)$_POST['type'],
                            'address' => trim((string)$_POST['address']),
                            'can_sell' => isset($_POST['can_sell']) ? 1 : 0,
                            'can_receive' => isset($_POST['can_receive']) ? 1 : 0,
                            'requested_by' => $_SESSION['username'] ?? 'Admin',
                            'requested_at' => date('Y-m-d H:i')
                        ];
                        $this->pdo()->prepare("UPDATE locations SET pending_edits = ? WHERE id = ?")
                             ->execute([json_encode($pendingData), $id]);
                        $this->flash('success', "Edits for '$name' submitted. They will remain inactive until approved by a Developer.");
                    }
                } elseif (isset($_POST['approve_edit'])) {
                    $this->requireRole(['dev']);
                    $id = (int)$_POST['location_id'];
                    $stmt = $this->pdo()->prepare("SELECT pending_edits FROM locations WHERE id = ?");
                    $stmt->execute([$id]);
                    $pendingRaw = $stmt->fetchColumn();
                    if ($pendingRaw) {
                        $pending = json_decode((string)$pendingRaw, true);
                        if ($pending) {
                            Location::update(
                                $id,
                                $pending['name'],
                                $pending['type'],
                                $pending['address'],
                                $pending['can_sell'],
                                $pending['can_receive']
                            );
                            $this->pdo()->prepare("UPDATE locations SET pending_edits = NULL WHERE id = ?")->execute([$id]);
                            $this->flash('success', "Pending edits applied to '{$pending['name']}'.");
                        }
                    }
                } elseif (isset($_POST['reject_edit'])) {
                    $this->requireRole(['dev']);
                    $id = (int)$_POST['location_id'];
                    $this->pdo()->prepare("UPDATE locations SET pending_edits = NULL WHERE id = ?")->execute([$id]);
                    $this->flash('success', "Pending edits rejected and deleted.");
                } elseif (($_POST['bulk_action'] ?? '') === 'delete') {
                    $this->verifyDevAuth($userRole);
                    $deleted = 0;
                    $blocked = 0;
                    foreach ($this->postedIds() as $id) {
                        $result = Location::delete($id);
                        if ($result === 'stock' || $result === 'sales') {
                            $blocked++;
                        } else {
                            $deleted++;
                        }
                    }
                    $msg = $deleted . ' location' . ($deleted === 1 ? '' : 's') . ' deleted.';
                    if ($blocked) {
                        $msg .= ' ' . $blocked . ' still have stock or sales and were kept.';
                    }
                    $this->flash($blocked && !$deleted ? 'error' : 'success', $msg);
                } elseif (isset($_POST['delete_location'])) {
                    $this->verifyDevAuth($userRole);
                    $result = Location::delete((int)$_POST['location_id']);
                    if ($result === 'stock') {
                        $this->flash('error', 'This location still has stock and cannot be deleted.');
                    } elseif ($result === 'sales') {
                        $this->flash('error', 'This location has sales history and cannot be deleted.');
                    } else {
                        $this->flash('success', 'Location deleted.');
                    }
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('locations');
        }

        // Fetch locations and decode any pending requests for the UI
        $locations = Location::all();
        $pendingCount = 0;
        foreach ($locations as &$l) {
            $l['pending'] = null;
            if (!empty($l['pending_edits'])) {
                $l['pending'] = json_decode($l['pending_edits'], true);
                $pendingCount++;
            }
        }
        unset($l);

        $this->render([
            'locations' => $locations,
            'userRole' => $userRole,
            'pendingCount' => $pendingCount
        ]);
    }

    private function ensurePendingColumn(): void
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'pending_edits'");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE locations ADD COLUMN pending_edits TEXT DEFAULT NULL");
        }
    }

    private function verifyDevAuth(string $userRole): void
    {
        if ($userRole === 'dev') return;
        $user = trim((string)($_POST['dev_username'] ?? ''));
        $pass = (string)($_POST['dev_password'] ?? '');

        if ($user === '' || $pass === '') {
            throw new \Exception('Developer authorization credentials are required for this action.');
        }

        $stmt = $this->pdo()->prepare('SELECT password, role FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$user]);
        $row = $stmt->fetch();

        if (!$row || $row['role'] !== 'dev' || !password_verify($pass, $row['password'])) {
            throw new \Exception('Developer authorization failed. Incorrect credentials.');
        }
    }
}
