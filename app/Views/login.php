<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmarTrade Enterprise</title>
    <?php $pwaStaffPart = 'head'; require APP_PATH . '/Views/partials/pwa_staff.php'; ?>

    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="js/app-alerts.js"></script>

    <style>
        :root {
            /* Odelia Enterprise Phoenix Palette */
            --brand-dark: #2c0e18;      /* Deep plum/brown from lower ring */
            --brand-crimson: #8b1c24;   /* Crimson mid-tone */
            --brand-primary: #d94e18;   /* Vibrant orange from phoenix body */
            --brand-accent: #df9422;    /* Gold from ODELIA text */
            
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --surface-color: #ffffff;
            --bg-color: #fdfbf7;
        }

        body {
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 100% 100%, rgba(223, 148, 34, 0.05) 0%, transparent 50%), 
                              radial-gradient(circle at 0% 0%, rgba(217, 78, 24, 0.05) 0%, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 1rem;
        }

        #pwaInstall { position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            background: var(--surface-color);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(44, 14, 24, 0.15);
            overflow: hidden;
            position: relative;
        }

        /* Branding Panel (Left Side) */
        .branding-panel {
            flex: 1.2;
            background: linear-gradient(145deg, var(--brand-dark) 0%, var(--brand-crimson) 40%, var(--brand-primary) 100%);
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        /* Animated Floating Background Elements (Left) */
        .floating-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .float-item {
            position: absolute;
            color: rgba(255, 255, 255, 0.08);
            animation: float-anim infinite alternate ease-in-out;
            white-space: nowrap;
            font-weight: 800;
            user-select: none;
        }

        .float-text { font-size: 1.5rem; letter-spacing: 2px; }
        .float-icon { font-size: 4rem; }

        @keyframes float-anim {
            0% { transform: translateY(0) scale(1) rotate(0deg); }
            100% { transform: translateY(-30px) scale(1.05) rotate(5deg); }
        }

        .fi-1 { top: 15%; left: 10%; animation-duration: 8s; font-size: 5rem; }
        .fi-2 { top: 25%; right: 15%; animation-duration: 10s; animation-delay: -2s; }
        .fi-3 { bottom: 20%; left: 15%; animation-duration: 9s; animation-delay: -4s; font-size: 4.5rem; }
        .fi-4 { bottom: 15%; right: 10%; animation-duration: 11s; animation-delay: -1s; }
        .fi-5 { top: 45%; left: -5%; animation-duration: 12s; animation-delay: -5s; font-size: 6rem; color: rgba(255,255,255,0.04); }
        .fi-6 { top: 60%; right: 5%; animation-duration: 7s; animation-delay: -3s; }

        .branding-content {
            z-index: 1;
            text-align: center;
            background: rgba(44, 14, 24, 0.2);
            padding: 2.5rem;
            border-radius: 20px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .brand-logo-large {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .brand-logo-large .accent {
            color: var(--brand-accent);
        }

        .brand-tagline {
            font-size: 1.1rem;
            opacity: 0.95;
            line-height: 1.5;
            color: #fdfbf7;
        }

        /* Form Panel (Right Side) - Dynamic Background */
        .form-panel {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            background: linear-gradient(-45deg, #ffffff, #fff3e0, #ffebee, #ffffff);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            z-index: 1;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Glassmorphism Card for the Form */
        .glass-form-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255, 255, 255, 1);
        }

        .form-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-header h2 {
            font-weight: 800;
            color: var(--brand-dark);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-muted);
            margin: 0;
        }

        .form-floating > label {
            color: var(--text-muted);
            padding-left: 1.25rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            height: auto;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.25rem rgba(217, 78, 24, 0.15);
            background: #ffffff;
        }

        .input-group-text {
            border: 2px solid #e9ecef;
            border-right: none;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-muted);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .input-group .form-control {
            border-left: none;
        }

        .btn-login {
            background: linear-gradient(to right, var(--brand-primary), var(--brand-accent));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(217, 78, 24, 0.3);
            margin-top: 1rem;
        }

        .btn-login:hover {
            background: linear-gradient(to right, var(--brand-crimson), var(--brand-primary));
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(217, 78, 24, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .footer-text {
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .odelia-link {
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 800;
            transition: color 0.2s ease;
        }

        .odelia-link:hover {
            color: var(--brand-dark);
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .branding-panel { padding: 3rem 2rem; }
            .form-panel { padding: 2rem; }
            .glass-form-card { padding: 2.5rem 1.5rem; }
            .brand-logo-large { font-size: 3rem; }
        }

        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; min-height: auto; }
            .branding-panel { padding: 4rem 1.5rem; }
            .brand-logo-large { font-size: 2.5rem; }
            .form-panel { padding: 1.5rem; }
            .floating-layer { opacity: 0.5; }
        }
    </style>
</head>
<body>
<?php $pwaStaffPart = 'bar'; require APP_PATH . '/Views/partials/pwa_staff.php'; ?>

<div class="login-wrapper">
    <!-- Branding Side -->
    <div class="branding-panel">
        <div class="floating-layer">
            <i class="bi bi-box-seam float-item float-icon fi-1"></i>
            <div class="float-item float-text fi-2">VSDC COMPLIANT</div>
            <i class="bi bi-receipt-cutoff float-item float-icon fi-3"></i>
            <div class="float-item float-text fi-4">MULTI-SITE</div>
            <i class="bi bi-shield-check float-item float-icon fi-5"></i>
            <div class="float-item float-text fi-6">INVENTORY</div>
        </div>

        <div class="branding-content">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" style="max-height:72px;max-width:220px;object-fit:contain;background:#fff;border-radius:12px;padding:6px;margin-bottom:12px;">
            <?php endif; ?>
            <div class="brand-logo-large">
                <i class="bi bi-buildings-fill"></i> SmarTrade<span class="accent">Enterprise</span>
            </div>
            <?php if (!empty($businessName)): ?>
                <div class="brand-tagline"><?= htmlspecialchars($businessName) ?></div>
            <?php endif; ?>
            <div class="brand-tagline">
                Enterprise Business & Retail Management System
            </div>
        </div>
    </div>

    <!-- Login Form Side -->
    <div class="form-panel">
        <div class="glass-form-card">
            <div class="form-header">
                <?php if (($step ?? 'password') === 'enroll'): ?>
                    <h2>Set up the sign-in code</h2>
                    <p>Scan this with an authenticator app, then enter the 6-digit code.</p>
                <?php elseif (($step ?? 'password') === 'code'): ?>
                    <h2>Enter the sign-in code</h2>
                    <p>Use the 6-digit code from the authenticator app, or a recovery code.</p>
                <?php elseif (($step ?? 'password') === 'recovery'): ?>
                    <h2>Save these recovery codes</h2>
                    <p>Each code works once if the phone is not available. They are not shown again.</p>
                <?php else: ?>
                    <h2>Welcome Back</h2>
                    <p>Please sign in to access your dashboard.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($error)): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    AppAlert.notify({
                        icon: 'error',
                        title: 'Authentication Failed',
                        text: <?= json_encode($error) ?>,
                        confirmText: 'Try Again'
                    });
                });
            </script>
            <?php endif; ?>

            <?php if (($step ?? 'password') === 'recovery'): ?>
            <ul class="list-unstyled mb-4" style="font-family: ui-monospace, monospace; letter-spacing: .04em;">
                <?php foreach ($recoveryCodes ?? [] as $recoveryCode): ?>
                    <li class="mb-1"><?= htmlspecialchars((string)$recoveryCode) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="POST" action="index.php?page=login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="acknowledge_recovery" value="1">
                <button type="submit" class="btn btn-login w-100">I saved these codes</button>
            </form>
            <?php elseif (in_array(($step ?? 'password'), ['enroll', 'code'], true)): ?>
            <?php if (($step ?? '') === 'enroll'): ?>
                <div id="totpQr" class="text-center mb-3"></div>
                <p class="small text-muted text-center mb-3">Or enter this key: <strong><?= htmlspecialchars((string)($totpSecret ?? '')) ?></strong></p>
            <?php endif; ?>
            <form method="POST" action="index.php?page=login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                    <div class="form-floating flex-grow-1">
                        <input type="text" name="totp_code" id="totp_code" class="form-control" required autofocus inputmode="numeric" autocomplete="one-time-code" placeholder="Code">
                        <label for="totp_code">Code</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-login w-100 d-flex justify-content-center align-items-center gap-2">
                    <span>Continue</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            <p class="text-center mt-3 mb-0"><a href="index.php?page=login&amp;restart=1">Start over</a></p>
            <?php else: ?>
            <form method="POST" action="index.php?page=login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <div class="form-floating flex-grow-1">
                        <input type="text" name="username" id="username" class="form-control" required autofocus placeholder="Username">
                        <label for="username">Username</label>
                    </div>
                </div>

                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                    <div class="form-floating flex-grow-1">
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Password">
                        <label for="password">Password</label>
                    </div>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-radius: 0 12px 12px 0; border: 2px solid #e9ecef; border-left: none; background: rgba(255,255,255,0.9);">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-login w-100 d-flex justify-content-center align-items-center gap-2">
                    <span>Sign In Securely</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            <?php endif; ?>

            <div class="footer-text">
                Powered by <a href="https://odeliazm.com" target="_blank" rel="noopener noreferrer" class="odelia-link">Odelia Enterprise <i class="bi bi-box-arrow-up-right" style="font-size: 0.8rem; margin-left: 2px;"></i></a>
            </div>
        </div>
    </div>
</div>

<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if (($step ?? '') === 'enroll' && !empty($totpUri)): ?>
<script src="js/qrcode.min.js"></script>
<script>
    (function () {
        var box = document.getElementById('totpQr');
        if (!box || typeof qrcode !== 'function') return;
        var qr = qrcode(0, 'M');
        qr.addData(<?= json_encode($totpUri) ?>, 'Byte');
        qr.make();
        box.innerHTML = qr.createImgTag(4, 8);
    })();
</script>
<?php endif; ?>
<script>
    var togglePassword = document.getElementById('togglePassword');
    if (togglePassword) togglePassword.addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
</script>

<script src="pwa/register.js"></script>
</body>
</html>
