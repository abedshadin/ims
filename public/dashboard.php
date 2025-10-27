<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();

$actions = [
    [
        'label' => 'Add Vendor',
        'description' => 'Capture a new vendor and their banking details.',
        'href' => 'vendor/create.php',
        'style' => 'primary',
    ],
    [
        'label' => 'Create File',
        'description' => 'Start a new import/export document for processing.',
        'href' => 'files/create.php',
        'style' => 'success',
    ],
    [
        'label' => 'Vendor List',
        'description' => 'Review the vendors that have already been captured.',
        'href' => 'vendor/index.php',
        'style' => 'info',
    ],
    [
        'label' => 'File List',
        'description' => 'Browse and manage existing files created in the system.',
        'href' => 'files/index.php',
        'style' => 'warning',
    ],
];

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
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Quickly access the most common vendor and file actions.</p>
        </div>
        <div class="text-md-end mt-3 mt-md-0">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="auth/logout.php">Sign Out</a>
        </div>
    </div>
    <div class="row g-4">
        <?php foreach ($actions as $action): ?>
            <div class="col-12 col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5"><?php echo e($action['label']); ?></h2>
                        <p class="text-muted flex-grow-1"><?php echo e($action['description']); ?></p>
                        <a class="btn btn-<?php echo e($action['style']); ?> mt-3 align-self-start" href="<?php echo e($action['href']); ?>">
                            <?php echo e($action['label']); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
