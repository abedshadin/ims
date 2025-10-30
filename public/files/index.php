<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();
$loadError = null;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = Database::getConnection();

    $statement = $pdo->query(
        'SELECT vf.id, vf.file_name, vf.bank_name, vf.brand, vf.created_at, v.vendor_name
         FROM vendor_files vf
         INNER JOIN vendors v ON v.id = vf.vendor_id
         ORDER BY vf.created_at DESC, vf.id DESC'
    );

    $fileRows = $statement->fetchAll() ?: [];
} catch (PDOException $exception) {
    $fileRows = [];
    $loadError = 'Files could not be loaded at this time. Please try again later.';
}

$files = [];

foreach ($fileRows as $row) {
    try {
        $token = IdCipher::encode((int) $row['id']);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        continue;
    }

    $files[] = [
        'token' => $token,
        'file_name' => (string) $row['file_name'],
        'vendor_name' => (string) $row['vendor_name'],
        'bank_name' => (string) $row['bank_name'],
        'brand' => (string) $row['brand'],
        'created_at' => date('j M Y, g:i A', strtotime((string) $row['created_at'])),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">File List</h1>
            <p class="text-muted mb-0">View files that have been initiated in the system.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/files/index.php'); ?>">Sign Out</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <?php if ($loadError !== null): ?>
            <div class="card-body">
                <div class="alert alert-danger mb-0" role="alert">
                    <?php echo e($loadError); ?>
                </div>
            </div>
        <?php elseif (empty($files)): ?>
            <div class="card-body text-center py-5">
                <h2 class="h5">No files yet</h2>
                <p class="text-muted mb-0">Create a file to begin capturing proforma invoices and product details.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">File</th>
                        <th scope="col">Vendor</th>
                        <th scope="col">Bank</th>
                        <th scope="col">Brand</th>
                        <th scope="col">Created</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td class="fw-semibold text-nowrap"><?php echo e($file['file_name']); ?></td>
                            <td><?php echo e($file['vendor_name']); ?></td>
                            <td class="text-nowrap"><?php echo e($file['bank_name']); ?></td>
                            <td class="text-nowrap text-uppercase"><?php echo e($file['brand']); ?></td>
                            <td class="text-muted text-nowrap"><?php echo e($file['created_at']); ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-primary btn-sm" href="show.php?file=<?php echo e($file['token']); ?>">
                                    Edit File
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
        <a class="btn btn-primary" href="create.php">Create File</a>
        <a class="btn btn-link" href="../dashboard.php">&#8592; Back to Dashboard</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
