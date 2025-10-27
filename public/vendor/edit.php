<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();
$encodedVendorId = isset($_GET['id']) ? (string) $_GET['id'] : '';
$vendorId = $encodedVendorId !== '' ? IdCipher::decode($encodedVendorId) : null;
$loadError = null;
$vendor = null;

if ($vendorId === null) {
    $loadError = 'A valid vendor was not specified.';
} else {
    try {
        $pdo = Database::getConnection();
        $statement = $pdo->prepare('SELECT * FROM vendors WHERE id = :id');
        $statement->execute([':id' => $vendorId]);
        $vendor = $statement->fetch();

        if (!$vendor) {
            $loadError = 'The requested vendor could not be found.';
        }
    } catch (\PDOException $exception) {
        $loadError = 'Unable to load vendor information right now. Please try again later.';
    }
}

$vendorToken = null;
if ($vendor && isset($vendor['id'])) {
    try {
        $vendorToken = IdCipher::encode((int) $vendor['id']);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $vendorToken = null;
    }
}

if ($vendor !== null && $vendorToken === null) {
    $loadError = 'Unable to prepare vendor details for editing. Please try again later.';
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vendor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Vendor</h1>
            <p class="text-muted mb-0">Update vendor account and banking information.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/vendor/edit.php'); ?>">Sign Out</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($loadError !== null): ?>
                <div class="alert alert-danger mb-0" role="alert"><?php echo e($loadError); ?></div>
            <?php else: ?>
                <form id="vendorForm" method="post" data-endpoint="update.php" data-reset-on-success="false" data-redirect="index.php" novalidate>
                    <?php if ($vendorToken !== null): ?>
                        <input type="hidden" name="vendor_id" value="<?php echo e($vendorToken); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="vendor_name">Vendor Name</label>
                            <input class="form-control" type="text" id="vendor_name" name="vendor_name" value="<?php echo e($vendor['vendor_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="vendor_address">Vendor Address</label>
                            <input class="form-control" type="text" id="vendor_address" name="vendor_address" value="<?php echo e($vendor['vendor_address']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="beneficiary_bank_name">Beneficiary Bank Name</label>
                            <input class="form-control" type="text" id="beneficiary_bank_name" name="beneficiary_bank_name" value="<?php echo e($vendor['beneficiary_bank_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="beneficiary_bank_address">Beneficiary Bank Address</label>
                            <input class="form-control" type="text" id="beneficiary_bank_address" name="beneficiary_bank_address" value="<?php echo e($vendor['beneficiary_bank_address']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="beneficiary_swift">Beneficiary SWIFT</label>
                            <input class="form-control" type="text" id="beneficiary_swift" name="beneficiary_swift" value="<?php echo e($vendor['beneficiary_swift']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="advising_bank_name">Advising Bank Name</label>
                            <input class="form-control" type="text" id="advising_bank_name" name="advising_bank_name" value="<?php echo e($vendor['advising_bank_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="advising_bank_account">Advising Bank Account</label>
                            <input class="form-control" type="text" id="advising_bank_account" name="advising_bank_account" value="<?php echo e($vendor['advising_bank_account']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="advising_swift_code">Advising SWIFT Code</label>
                            <input class="form-control" type="text" id="advising_swift_code" name="advising_swift_code" value="<?php echo e($vendor['advising_swift_code']); ?>" required>
                        </div>
                    </div>
                    <div id="formAlert" class="alert d-none" role="alert"></div>
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Save Changes</button>
                        <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <a class="btn btn-link" href="../dashboard.php">&#8592; Back to Dashboard</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/vendor_form.js"></script>
</body>
</html>
