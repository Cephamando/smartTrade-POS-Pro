<?php

namespace App\Controllers;

use App\Helpers\Media;
use App\Models\Setting;
use App\Models\User;
use App\Services\Totp;

final class LoginController extends Controller
{
    public function index(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $error = null;
        if (isset($_GET['restart'])) {
            $this->clearPending();
        }
        $step = $this->pendingStep();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
                $error = 'Your session expired. Refresh the page and try again.';
            } elseif (isset($_POST['totp_code']) || isset($_POST['acknowledge_recovery'])) {
                $error = $this->finishSecondStep();
                $step = $this->pendingStep();
            } else {
                $error = $this->checkPassword();
                $step = $this->pendingStep();
            }
        }

        $settings = Setting::all();
        $secret = (string)($_SESSION['pending_totp_secret'] ?? '');
        $username = (string)($_SESSION['pending_username'] ?? '');
        $issuer = (string)($settings['business_name'] ?? 'smartTrade');
        $this->render([
            'error' => $error,
            'step' => $step,
            'logoUrl' => Media::url((string)($settings['logo_path'] ?? '')),
            'businessName' => (string)($settings['business_name'] ?? ''),
            'totpSecret' => $step === 'enroll' ? $secret : '',
            'totpUri' => $step === 'enroll' && $secret !== '' ? Totp::uri($secret, $username, $issuer) : '',
            'recoveryCodes' => $step === 'recovery' ? (array)($_SESSION['recovery_plain'] ?? []) : [],
        ]);
    }

    private function pendingStep(): string
    {
        if (empty($_SESSION['pending_user_id'])) {
            return 'password';
        }
        if (!empty($_SESSION['recovery_plain'])) {
            return 'recovery';
        }
        if (!empty($_SESSION['pending_totp_secret'])) {
            return 'enroll';
        }
        return 'code';
    }

    private function checkPassword(): ?string
    {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            return 'Enter your username and password.';
        }
        $user = User::findActiveByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            \App\Services\Activity::log($this->pdo(), 'login', 'failed', [
                'user_id' => is_array($user) ? (int)$user['id'] : 0,
                'username' => $username,
                'subject' => 'Sign-in failed',
            ]);
            return 'Username or password is incorrect.';
        }

        $this->clearPending();
        if (User::officeRole((string)$user['role'])) {
            $_SESSION['pending_user_id'] = (int)$user['id'];
            $_SESSION['pending_username'] = (string)$user['username'];
            $_SESSION['pending_role'] = (string)$user['role'];
            $_SESSION['pending_location_id'] = $user['location_id'];
            $_SESSION['pending_location_name'] = (string)($user['location_name'] ?? '');
            $_SESSION['pending_force'] = (int)$user['force_password_change'];
            $_SESSION['pending_at'] = time();
            $secret = trim((string)($user['totp_secret'] ?? ''));
            if ($secret === '') {
                $_SESSION['pending_totp_secret'] = Totp::secret();
            }
            return null;
        }

        $this->completeLogin($user);
        return null;
    }

    private function finishSecondStep(): ?string
    {
        $userId = (int)($_SESSION['pending_user_id'] ?? 0);
        $started = (int)($_SESSION['pending_at'] ?? 0);
        if ($userId <= 0 || $started <= 0 || time() - $started > 300) {
            $this->clearPending();
            return 'That sign-in expired. Enter your password again.';
        }
        if (isset($_POST['acknowledge_recovery'])) {
            unset($_SESSION['recovery_plain']);
            $user = User::findById($userId);
            if (!$user) {
                $this->clearPending();
                return 'Sign in again.';
            }
            $this->completeLogin($this->pendingAsUser($user));
            return null;
        }

        $code = trim((string)($_POST['totp_code'] ?? ''));
        if (!empty($_SESSION['pending_totp_secret'])) {
            $secret = (string)$_SESSION['pending_totp_secret'];
            if (!Totp::verify($secret, $code)) {
                return 'That code was not accepted. Use the code from the authenticator app.';
            }
            $plain = Totp::recoveryCodes();
            $hashes = [];
            foreach ($plain as $recovery) {
                $hashes[] = password_hash($recovery, PASSWORD_DEFAULT);
            }
            User::saveSignInCode($userId, $secret, $hashes);
            unset($_SESSION['pending_totp_secret']);
            $_SESSION['recovery_plain'] = $plain;
            $_SESSION['pending_at'] = time();
            return null;
        }

        $user = User::findById($userId);
        $secret = trim((string)($user['totp_secret'] ?? ''));
        if ($secret !== '' && Totp::verify($secret, $code)) {
            $this->completeLogin($this->pendingAsUser($user));
            return null;
        }
        if (User::consumeRecoveryCode($userId, $code)) {
            $this->completeLogin($this->pendingAsUser($user ?: []));
            return null;
        }
        return 'That code was not accepted.';
    }

    /** @param array<string, mixed> $user */
    private function pendingAsUser(array $user): array
    {
        $user['id'] = (int)($_SESSION['pending_user_id'] ?? ($user['id'] ?? 0));
        $user['username'] = (string)($_SESSION['pending_username'] ?? ($user['username'] ?? ''));
        $user['role'] = (string)($_SESSION['pending_role'] ?? ($user['role'] ?? ''));
        $user['location_id'] = $_SESSION['pending_location_id'] ?? ($user['location_id'] ?? null);
        $user['location_name'] = (string)($_SESSION['pending_location_name'] ?? ($user['location_name'] ?? ''));
        $user['force_password_change'] = (int)($_SESSION['pending_force'] ?? ($user['force_password_change'] ?? 0));
        $user['session_rev'] = User::sessionRev((int)$user['id']);
        return $user;
    }

    /** @param array<string, mixed> $user */
    private function completeLogin(array $user): void
    {
        $this->clearPending();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = (string)$user['role'];
        $_SESSION['location_id'] = $user['location_id'];
        $_SESSION['location_name'] = (string)($user['location_name'] ?? '');
        $_SESSION['force_change'] = (int)($user['force_password_change'] ?? 0);
        $_SESSION['session_rev'] = (int)($user['session_rev'] ?? User::sessionRev((int)$user['id']));
        $_SESSION['last_seen'] = time();
        \App\Services\Activity::log($this->pdo(), 'login', 'ok', [
            'user_id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'location_id' => (int)($user['location_id'] ?? 0),
            'location_name' => (string)($user['location_name'] ?? ''),
            'subject' => 'Signed in',
        ]);

        if ((int)($user['force_password_change'] ?? 0) === 1) {
            $this->redirect('change_password');
        }
        if (User::officeRole((string)$user['role'])) {
            $this->redirect('dashboard');
        }
        if (in_array((string)$user['role'], ['chef', 'head_chef'], true)) {
            $this->redirect('kitchen');
        }
        $this->redirect('pos');
    }

    private function clearPending(): void
    {
        unset(
            $_SESSION['pending_user_id'],
            $_SESSION['pending_username'],
            $_SESSION['pending_role'],
            $_SESSION['pending_location_id'],
            $_SESSION['pending_location_name'],
            $_SESSION['pending_force'],
            $_SESSION['pending_at'],
            $_SESSION['pending_totp_secret'],
            $_SESSION['recovery_plain']
        );
    }
}
