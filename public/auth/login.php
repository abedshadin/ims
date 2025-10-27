<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Redirect.php';

$requestedRedirect = sanitize_redirect_target($_GET['redirect'] ?? '');
$redirectDestination = resolve_redirect_target($requestedRedirect, '../dashboard.php');
$errors = [];
$successMessage = '';

if (isset($_GET['registered'])) {
    $successMessage = 'Registration successful. Please sign in.';
}

if (Auth::check()) {
    header('Location: ' . $redirectDestination);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRedirect = sanitize_redirect_target($_POST['redirect'] ?? '');

    if ($postedRedirect !== '') {
        $requestedRedirect = $postedRedirect;
    }

    $redirectDestination = resolve_redirect_target($requestedRedirect, '../dashboard.php');
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!Auth::attempt($email, $password)) {
        $errors[] = 'Invalid email or password.';
    } else {
        header('Location: ' . $redirectDestination);
        exit;
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Welcome Back</h1>
                </div>
                <div class="card-body">
                    <?php if ($successMessage !== ''): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo e($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo e($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <input type="hidden" name="redirect" value="<?php echo e($redirectDestination); ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus value="<?php echo e($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Sign In</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">Don't have an account? <a href="register.php<?php echo $redirectDestination ? '?redirect=' . urlencode($redirectDestination) : ''; ?>">Create one</a>.</small>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
