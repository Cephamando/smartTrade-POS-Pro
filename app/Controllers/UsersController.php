<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Shift;
use App\Models\StaffSession;
use App\Models\User;
use App\Services\Activity;

final class UsersController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'dev', 'owner'], true)) {
            $this->flash('error', 'You do not have permission to manage users.');
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['sign_out_session']) || isset($_POST['sign_out_location']) || isset($_POST['sign_out_everyone'])) {
                $this->signOutStaff();
            }
            if (isset($_POST['end_open_shift']) || isset($_POST['end_open_shifts_location']) || isset($_POST['end_open_shifts_all'])) {
                $this->endOpenShifts();
            }
            if (($_POST['bulk_action'] ?? '') === 'delete') {
                $deleted = 0;
                $deactivated = 0;
                $skipped = 0;
                foreach ($this->postedIds() as $userId) {
                    $target = User::findById($userId);
                    $targetRole = $target['role'] ?? '';
                    if ($targetRole === 'dev' || $userId === $this->userId()) {
                        $skipped++;
                        continue;
                    }
                    $result = User::deleteOrDeactivate($userId);
                    if ($result === 'deactivated') {
                        $deactivated++;
                    } else {
                        $deleted++;
                    }
                }
                $parts = [];
                if ($deleted) {
                    $parts[] = $deleted . ' deleted';
                }
                if ($deactivated) {
                    $parts[] = $deactivated . ' deactivated (they have history)';
                }
                if ($skipped) {
                    $parts[] = $skipped . ' left unchanged';
                }
                $this->flash('success', $parts ? implode(', ', $parts) . '.' : 'Nothing was selected.');
                $this->redirect('users');
            }
            if (isset($_POST['reset_sign_in'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = (string)($target['role'] ?? '');
                if (!$target || !User::officeRole($targetRole)) {
                    $this->flash('error', 'That account does not use a sign-in code.');
                } elseif ($targetRole === 'dev' && $this->role() !== 'dev') {
                    $this->flash('error', 'The developer account cannot be modified.');
                } else {
                    User::clearSignInCode($userId);
                    $this->flash('success', 'Sign-in code reset. The next sign-in will set a new one.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['add_user'])) {
                $role = (string)$_POST['role'];
                if ($role === 'dev' && $this->role() !== 'dev') {
                    $role = 'admin';
                }
                $password = (string)$_POST['password'];
                $code = trim((string)($_POST['approval_code'] ?? ''));
                if (!User::passwordOk($password)) {
                    $this->flash('error', 'The password must be at least 6 characters.');
                    $this->redirect('users');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if (User::approverRole($role)) {
                    $problem = $code === '' ? 'This role needs an approval code for the till.' : User::approvalCodeError($code, $passwordHash);
                    if ($problem !== null) {
                        $this->flash('error', $problem);
                        $this->redirect('users');
                    }
                }
                try {
                    $newId = User::create(
                        trim((string)$_POST['username']),
                        trim((string)$_POST['full_name']),
                        $passwordHash,
                        $role,
                        (int)$_POST['location_id']
                    );
                    if ($code !== '' && User::approverRole($role)) {
                        User::setApprovalCode($newId, $code);
                    }
                    $this->flash('success', 'User created.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'That username is already in use.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['edit_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev' && $this->role() !== 'dev') {
                    $this->flash('error', 'The developer account cannot be modified.');
                    $this->redirect('users');
                }
                $role = (string)$_POST['role'];
                if ($targetRole === 'dev' && $role !== 'dev') {
                    $role = 'dev';
                }
                $password = (string)($_POST['password'] ?? '');
                $code = trim((string)($_POST['approval_code'] ?? ''));
                if ($password !== '' && !User::passwordOk($password)) {
                    $this->flash('error', 'The password must be at least 6 characters.');
                    $this->redirect('users');
                }
                $newHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                $checkHash = $newHash ?? (string)($target['password_hash'] ?? '');
                $hasApproval = trim((string)($target['approval_code_hash'] ?? '')) !== '';
                if (User::approverRole($role) && $code === '' && !$hasApproval) {
                    $this->flash('error', 'This role needs an approval code for the till.');
                    $this->redirect('users');
                }
                if ($code !== '') {
                    $problem = User::approvalCodeError($code, $checkHash);
                    if ($problem !== null) {
                        $this->flash('error', $problem);
                        $this->redirect('users');
                    }
                }
                try {
                    User::update($userId, trim((string)$_POST['username']), trim((string)$_POST['full_name']), $role, (int)$_POST['location_id'], $newHash);
                    if ($code !== '') {
                        User::setApprovalCode($userId, $code);
                    }
                    if ($newHash && $userId === $this->userId()) {
                        $_SESSION['session_rev'] = User::sessionRev($userId);
                        $_SESSION['last_seen'] = time();
                    }
                    $this->flash('success', 'User updated.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'Unable to update this user.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['delete_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev') {
                    $this->flash('error', 'The developer account cannot be deleted.');
                } elseif ($userId === $this->userId()) {
                    $this->flash('error', 'You cannot delete the signed-in account.');
                } else {
                    try {
                        $result = User::deleteOrDeactivate($userId);
                        if ($result === 'deactivated') {
                            $this->flash('info', 'This user has history, so the account was deactivated.');
                        } else {
                            $this->flash('success', 'User deleted.');
                        }
                    } catch (\Throwable $e) {
                        $this->flash('error', 'Unable to delete this user.');
                    }
                }
                $this->redirect('users');
            }
        }

        $signedIn = [];
        $openShifts = [];
        if ($this->role() === 'dev') {
            try {
                $keep = (string)($_SESSION['session_token'] ?? '');
                foreach (StaffSession::online() as $row) {
                    $row['yours'] = $keep !== '' && $row['token'] === $keep;
                    unset($row['token']);
                    $signedIn[] = $row;
                }
            } catch (\Throwable $e) {
                $signedIn = [];
            }
            try {
                $openShifts = Shift::openNow();
            } catch (\Throwable $e) {
                $openShifts = [];
            }
        }

        $this->render([
            'users' => User::activeStaff(),
            'locations' => Location::names(),
            'signedIn' => $signedIn,
            'openShifts' => $openShifts,
        ]);
    }

    /** A developer ends one browser, every browser at a workstation, or every browser but their own. */
    private function signOutStaff(): void
    {
        if ($this->role() !== 'dev') {
            $this->flash('error', 'Only a developer can sign staff out.');
            $this->redirect('users');
        }
        $keep = (string)($_SESSION['session_token'] ?? '');
        try {
            if (isset($_POST['sign_out_session'])) {
                $row = StaffSession::signOutOne((int)($_POST['session_id'] ?? 0));
                if (!$row) {
                    $this->flash('info', 'That sign-in has already ended.');
                } else {
                    Activity::log($this->pdo(), 'sign_out', 'ok', [
                        'subject' => 'Signed out',
                        'detail' => $row['who'],
                    ]);
                    $this->flash('success', $row['who'] . ' was signed out.');
                }
            } elseif (isset($_POST['sign_out_location']) && (string)($_POST['location_id'] ?? '') === '') {
                $this->flash('error', 'Choose a workstation first.');
            } else {
                $locationId = isset($_POST['sign_out_everyone']) ? null : (int)$_POST['location_id'];
                $names = StaffSession::signOutGroup($keep, $locationId);
                $place = '';
                if ($locationId !== null) {
                    $place = $locationId > 0 ? Location::nameById($locationId) : 'No workstation';
                }
                if (!$names) {
                    $this->flash('info', $place === '' ? 'Nobody else is signed in.' : 'Nobody else is signed in at ' . $place . '.');
                } else {
                    $count = count($names);
                    $who = $count === 1 ? $names[0] . ' was' : $count . ' people were';
                    $where = $place === '' ? '' : ' at ' . $place;
                    Activity::log($this->pdo(), 'sign_out', 'ok', [
                        'subject' => 'Signed out',
                        'detail' => ($place === '' ? 'Everyone else' : $place) . ': ' . implode(', ', $names),
                    ]);
                    $this->flash('success', $who . ' signed out' . $where . '.');
                }
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'Unable to sign them out.');
        }
        $this->redirect('users');
    }

    /** A developer closes open shifts without a drawer count. Staff stay signed in. */
    private function endOpenShifts(): void
    {
        if ($this->role() !== 'dev') {
            $this->answerEndShift('error', 'Only a developer can end someone else\'s shift.');
        }
        $type = 'error';
        $message = 'Unable to end the shift.';
        try {
            if (isset($_POST['end_open_shift'])) {
                $row = Shift::closeUncounted((int)($_POST['shift_id'] ?? 0), $this->userId());
                if (!$row) {
                    $type = 'info';
                    $message = 'That shift is no longer open.';
                } else {
                    Activity::log($this->pdo(), 'shift', 'ok', [
                        'subject' => 'Shift closed',
                        'detail' => $row['who'] . ' at ' . $row['location'] . '. Drawer not counted.',
                    ]);
                    $type = 'success';
                    $message = $row['who'] . ' at ' . $row['location'] . ' was ended. The drawer was not counted.';
                }
            } elseif (isset($_POST['end_open_shifts_location']) && (string)($_POST['shift_location_id'] ?? '') === '') {
                $message = 'Choose a workstation first.';
            } else {
                $locationId = isset($_POST['end_open_shifts_all']) ? null : (int)$_POST['shift_location_id'];
                $rows = Shift::closeUncountedGroup($locationId, $this->userId());
                $place = $locationId !== null && $locationId > 0 ? Location::nameById($locationId) : '';
                if (!$rows) {
                    $type = 'info';
                    $message = $place === '' ? 'There are no open shifts.' : 'There are no open shifts at ' . $place . '.';
                } else {
                    $count = count($rows);
                    $names = array_map(static fn(array $row): string => $row['who'] . ' at ' . $row['location'], $rows);
                    Activity::log($this->pdo(), 'shift', 'ok', [
                        'subject' => 'Shifts closed',
                        'detail' => ($place === '' ? 'Every open shift' : $place) . ': ' . implode(', ', $names),
                    ]);
                    if ($count === 1) {
                        $sentence = $names[0] . ' was ended.';
                    } elseif ($place !== '') {
                        $sentence = $count . ' shifts at ' . $place . ' were ended.';
                    } else {
                        $sentence = $count . ' shifts were ended.';
                    }
                    $type = 'success';
                    $message = $sentence . ' The drawer was not counted.';
                }
            }
        } catch (\Throwable $e) {
            $type = 'error';
            $message = 'Unable to end the shift.';
        }
        $this->answerEndShift($type, $message);
    }

    /**
     * A background request gets the result and the refreshed Open shifts card,
     * so the page updates in place. A plain form post still reloads the page.
     */
    private function answerEndShift(string $type, string $message): never
    {
        if (!$this->wantsAjax()) {
            $this->flash($type, $message);
            $this->redirect('users');
        }
        $answer = ['status' => $type, 'msg' => $message];
        if ($this->role() === 'dev') {
            try {
                $answer['html'] = $this->renderPartial('open_shifts', ['openShifts' => Shift::openNow()]);
            } catch (\Throwable $e) {
                // Leave the card as it is. The message still reports what happened.
            }
        }
        $this->jsonExit($answer);
    }
}
