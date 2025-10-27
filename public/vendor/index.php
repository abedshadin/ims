<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();
$vendors = [];
$loadError = null;

try {
    $pdo = Database::getConnection();
    $statement = $pdo->query(
        'SELECT v.id, v.vendor_name, v.vendor_address, v.beneficiary_bank_name, v.created_at, u.name AS created_by_name ' .
        'FROM vendors v ' .
        'LEFT JOIN users u ON u.id = v.created_by ' .
        'ORDER BY v.created_at DESC'
    );
    $vendors = $statement->fetchAll();
} catch (\PDOException $exception) {
    $loadError = 'Unable to load vendors right now. Please try again later.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('M j, Y g:i A', $timestamp) : $value;
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
            <?php if ($loadError !== null): ?>
                <div class="alert alert-danger mb-0" role="alert"><?php echo e($loadError); ?></div>
            <?php elseif (empty($vendors)): ?>
                <p class="text-muted mb-0">No vendors have been captured yet. Use the button below to add the first vendor.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Vendor</th>
                                <th scope="col">Beneficiary Bank</th>
                                <th scope="col">Created</th>
                                <th scope="col">Created By</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($vendor['vendor_name']); ?></div>
                                        <div class="text-muted small"><?php echo e($vendor['vendor_address']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($vendor['beneficiary_bank_name']); ?></div>
                                    </td>
                                    <td><?php echo e(formatDate($vendor['created_at'])); ?></td>
                                    <td><?php echo e($vendor['created_by_name'] ?? '—'); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="edit.php?id=<?php echo urlencode((string) $vendor['id']); ?>">Edit Info</a>
                                            <a class="btn btn-outline-secondary btn-sm" href="products.php?vendor_id=<?php echo urlencode((string) $vendor['id']); ?>">Add Products</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
