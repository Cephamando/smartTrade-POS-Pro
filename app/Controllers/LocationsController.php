<?php

namespace App\Controllers;

use App\Models\Location;

final class LocationsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'dev']);
        Location::ensureSchema();

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
                    if ($name !== '') {
                        Location::update(
                            $id,
                            $name,
                            (string)$_POST['type'], 
                            trim((string)$_POST['address']),
                            isset($_POST['can_sell']) ? 1 : 0,
                            isset($_POST['can_receive']) ? 1 : 0
                        );
                        $this->flash('success', "Location '$name' updated.");
                    }
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

        $this->render([
            'locations' => Location::all(),
            'userRole' => $userRole
        ]);
    }

    private function verifyDevAuth(string $userRole): void
    {
        // Automatically pass if the user is already logged in as a dev
        if ($userRole === 'dev') {
            return;
        }

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