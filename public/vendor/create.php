<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Vendor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-end mb-3">
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? 'vendor/create.php'); ?>">Sign Out</a>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Vendor Information</h1>
                </div>
                <div class="card-body">
                    <div id="formAlert" class="alert d-none" role="alert"></div>
                    <form id="vendorForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="vendor_name" class="form-label">Vendor Name</label>
                                <input type="text" class="form-control" id="vendor_name" name="vendor_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="vendor_address" class="form-label">Vendor Address</label>
                                <input type="text" class="form-control" id="vendor_address" name="vendor_address" required>
                            </div>
                            <div class="col-md-6">
                                <label for="beneficiary_bank_name" class="form-label">Beneficiary Bank Name</label>
                                <input type="text" class="form-control" id="beneficiary_bank_name" name="beneficiary_bank_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="beneficiary_bank_address" class="form-label">Beneficiary Bank Address</label>
                                <input type="text" class="form-control" id="beneficiary_bank_address" name="beneficiary_bank_address" required>
                            </div>
                            <div class="col-md-6">
                                <label for="beneficiary_swift" class="form-label">Beneficiary SWIFT</label>
                                <input type="text" class="form-control" id="beneficiary_swift" name="beneficiary_swift" required>
                            </div>
                            <div class="col-md-6">
                                <label for="advising_bank_name" class="form-label">Advising Bank Name</label>
                                <input type="text" class="form-control" id="advising_bank_name" name="advising_bank_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="advising_bank_account" class="form-label">Advising Bank Account Number</label>
                                <input type="text" class="form-control" id="advising_bank_account" name="advising_bank_account" required>
                            </div>
                            <div class="col-md-6">
                                <label for="advising_swift_code" class="form-label">Advising SWIFT Code</label>
                                <input type="text" class="form-control" id="advising_swift_code" name="advising_swift_code" required>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <button type="reset" class="btn btn-outline-secondary">Clear</button>
                            <button type="submit" class="btn btn-primary">Save Vendor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/vendor_form.js"></script>
</body>
</html>
