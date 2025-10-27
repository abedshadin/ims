<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileReference.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$vendors = [];
$fileNameSuggestion = '';
$loadError = null;

try {
    $pdo = Database::getConnection();

    $vendorStatement = $pdo->query('SELECT id, vendor_name FROM vendors ORDER BY vendor_name');
    $vendors = $vendorStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $fileNameSuggestion = FileReference::next($pdo);
} catch (PDOException $exception) {
    $loadError = 'We could not load the information required to create a file right now. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create File</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body {
            background: radial-gradient(circle at top left, rgba(13,110,253,0.12), transparent 45%),
                        radial-gradient(circle at bottom right, rgba(25,135,84,0.12), transparent 40%);
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Create New File</h1>
            <p class="text-muted mb-0">Generate a trading file by pairing a vendor with the appropriate bank and brand.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/files/create.php'); ?>">Sign Out</a>
        </div>
    </div>

    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo e($loadError); ?>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="badge bg-light text-primary rounded-pill px-3 py-2 text-uppercase fw-semibold">File Setup</div>
                        <h2 class="h4 mt-3 mb-1">File Details</h2>
                        <p class="text-muted mb-0">All fields are required to create a new file.</p>
                    </div>
                    <form id="vendorForm" method="post" data-endpoint="../vendor/files_store.php" data-reset-on-success="true" data-open-file-url="show.php?file=" novalidate>
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label text-uppercase small fw-semibold" for="file_name">File Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white fw-semibold">Auto</span>
                                    <input class="form-control form-control-lg" type="text" id="file_name" name="file_name" value="<?php echo e($fileNameSuggestion); ?>" readonly data-auto-file-name="true" placeholder="F/<?php echo date('Y'); ?>/1">
                                </div>
                                <div class="form-text">File names follow the format F/<?php echo date('Y'); ?>/N and increase automatically.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase small fw-semibold" for="vendor_id">Vendor</label>
                                <select class="form-select form-select-lg" id="vendor_id" name="vendor_id" required <?php echo $loadError || empty($vendors) ? 'disabled' : ''; ?>>
                                    <option value="" selected>Select a vendor</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo e(IdCipher::encode((int) $vendor['id'])); ?>"><?php echo e($vendor['vendor_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($vendors) && !$loadError): ?>
                                    <div class="form-text text-danger">Add a vendor first to create files.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold" for="bank_name">Bank Name</label>
                                <select class="form-select form-select-lg" id="bank_name" name="bank_name" required <?php echo $loadError ? 'disabled' : ''; ?>>
                                    <option value="" selected>Select bank</option>
                                    <option value="DBBL">DBBL</option>
                                    <option value="SCB">SCB</option>
                                    <option value="BBL">BBL</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold d-block">Brand</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="brand" id="brand_ph" value="PH" autocomplete="off" required <?php echo $loadError ? 'disabled' : ''; ?>>
                                    <label class="btn btn-outline-primary" for="brand_ph">PH</label>
                                    <input type="radio" class="btn-check" name="brand" id="brand_kfc" value="KFC" autocomplete="off" <?php echo $loadError ? 'disabled' : ''; ?>>
                                    <label class="btn btn-outline-primary" for="brand_kfc">KFC</label>
                                    <input type="radio" class="btn-check" name="brand" id="brand_ph_kfc" value="PH/KFC" autocomplete="off" <?php echo $loadError ? 'disabled' : ''; ?>>
                                    <label class="btn btn-outline-primary" for="brand_ph_kfc">PH/KFC</label>
                                </div>
                            </div>
                        </div>
                        <div id="formAlert" class="alert d-none" role="alert"></div>
                        <div class="mt-4 d-flex flex-column flex-sm-row gap-2">
                            <button class="btn btn-primary btn-lg" type="submit" <?php echo $loadError ? 'disabled' : ''; ?>>Create File</button>
                            <a class="btn btn-outline-secondary btn-lg" href="../dashboard.php">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
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
