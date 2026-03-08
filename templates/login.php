<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Odelia POS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" integrity="sha512-ZnR2wlLbSbr8/c9AgLg3jQPAattCUImNsae6NHYnS9KrIwRdcY9DxFotXhNAKIKbAXlRnujIqUWoXXwqyFOeIQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .brand-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
            text-align: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-logo">
        <i class="bi bi-layer-group"></i> Odelia<span class="text-primary">POS</span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login">
        
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="mb-3">
            <label class="form-label text-muted">Username</label>
            <input type="text" name="username" class="form-control form-control-lg" required autofocus placeholder="e.g. admin">
        </div>

        <div class="mb-4">
            <label class="form-label text-muted">Password</label>
            <input type="password" name="password" class="form-control form-control-lg" required placeholder="******">
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg fw-bold">Sign In</button>
    </form>
    
    <div class="text-center mt-4 text-muted small">
        Powered by <strong>Cyberarena Live Tech.</strong>
    </div>
</div>

</body>
</html>
