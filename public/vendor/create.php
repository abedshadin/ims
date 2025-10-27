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
    <title>Create Vendor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Create Vendor</h1>
            <p class="text-muted mb-0">Capture a new vendor record to track future transactions.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/vendor/create.php'); ?>">Sign Out</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="vendorForm" method="post" data-endpoint="store.php" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="vendor_name">Vendor Name</label>
                        <input class="form-control" type="text" id="vendor_name" name="vendor_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vendor_address">Vendor Address</label>
                        <input class="form-control" type="text" id="vendor_address" name="vendor_address" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="beneficiary_bank_name">Beneficiary Bank Name</label>
                        <input class="form-control" type="text" id="beneficiary_bank_name" name="beneficiary_bank_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="beneficiary_bank_address">Beneficiary Bank Address</label>
                        <input class="form-control" type="text" id="beneficiary_bank_address" name="beneficiary_bank_address" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="beneficiary_swift">Beneficiary SWIFT</label>
                        <input class="form-control" type="text" id="beneficiary_swift" name="beneficiary_swift" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="advising_bank_name">Advising Bank Name</label>
                        <input class="form-control" type="text" id="advising_bank_name" name="advising_bank_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="advising_bank_account">Advising Bank Account</label>
                        <input class="form-control" type="text" id="advising_bank_account" name="advising_bank_account" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="advising_swift_code">Advising SWIFT Code</label>
                        <input class="form-control" type="text" id="advising_swift_code" name="advising_swift_code" required>
                    </div>
                </div>
                <div id="formAlert" class="alert d-none" role="alert"></div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save Vendor</button>
                    <a class="btn btn-outline-secondary" href="index.php">View Vendors</a>
                </div>
            </form>
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
