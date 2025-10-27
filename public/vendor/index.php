<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();

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
    <title>Vendor List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Vendor List</h1>
            <p class="text-muted mb-0">Keep track of every vendor entered in the system.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/vendor/index.php'); ?>">Sign Out</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted mb-0">Vendor records will display here once the listing feature is implemented.</p>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <a class="btn btn-primary" href="create.php">Add Vendor</a>
        <a class="btn btn-link" href="../dashboard.php">&#8592; Back to Dashboard</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
