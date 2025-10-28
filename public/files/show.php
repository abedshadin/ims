<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';
require_once __DIR__ . '/../../app/FileLcDetails.php';
require_once __DIR__ . '/../../app/BankDirectory.php';

Auth::requireLogin('/auth/login.php');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$currentUserName = Auth::userName();
$fileToken = isset($_GET['file']) ? (string) $_GET['file'] : '';
$fileId = $fileToken !== '' ? IdCipher::decode($fileToken) : null;
$loadError = null;
$file = null;
$vendorProducts = [];
$proformas = [];
$lcDetails = null;
$bankProfile = null;
$bankReference = null;

if ($fileId === null) {
    $loadError = 'A valid file reference was not provided.';
} else {
    try {
        $pdo = Database::getConnection();

        $file = FileMetadata::load($pdo, $fileId);

        if ($file === null) {
            $loadError = 'The requested file could not be found.';
        } else {
            $vendorId = (int) $file['vendor_id'];

            $bankProfile = $file['bank_profile'] ?? null;
            $bankReference = $file['bank_reference'] ?? null;

            $vendorProductsStatement = $pdo->prepare(
                'SELECT id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code
                 FROM vendor_products
                 WHERE vendor_id = :vendor_id
                 ORDER BY product_name ASC'
            );
            $vendorProductsStatement->execute([':vendor_id' => $vendorId]);
            $vendorProducts = $vendorProductsStatement->fetchAll() ?: [];

            $proformaStatement = $pdo->prepare(
                'SELECT id, invoice_number, freight_amount, created_at
                 FROM proforma_invoices
                 WHERE vendor_file_id = :file_id
                 ORDER BY created_at DESC, id DESC'
            );
            $proformaStatement->execute([':file_id' => $fileId]);
            $proformaRows = $proformaStatement->fetchAll() ?: [];

            if (!empty($proformaRows)) {
                $proformaIds = array_map(static fn(array $row): int => (int) $row['id'], $proformaRows);
                $placeholders = implode(',', array_fill(0, count($proformaIds), '?'));

                $productsStatement = $pdo->prepare(
                    "SELECT id, proforma_invoice_id, vendor_product_id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code, quantity, fob_total, created_at
                     FROM proforma_invoice_products
                     WHERE proforma_invoice_id IN ($placeholders)
                     ORDER BY created_at ASC, id ASC"
                );
                $productsStatement->execute($proformaIds);
                $productsByInvoice = [];

                while ($productRow = $productsStatement->fetch()) {
                    $productsByInvoice[(int) $productRow['proforma_invoice_id']][] = $productRow;
                }
            } else {
                $productsByInvoice = [];
            }

            foreach ($proformaRows as $row) {
                try {
                    $encodedId = IdCipher::encode((int) $row['id']);
                } catch (InvalidArgumentException|RuntimeException $exception) {
                    continue;
                }

                $products = [];

                foreach ($productsByInvoice[(int) $row['id']] ?? [] as $productRow) {
                    $vendorProductToken = null;

                    if (!empty($productRow['vendor_product_id'])) {
                        try {
                            $vendorProductToken = IdCipher::encode((int) $productRow['vendor_product_id']);
                        } catch (InvalidArgumentException|RuntimeException $exception) {
                            $vendorProductToken = null;
                        }
                    }

                    try {
                        $productToken = IdCipher::encode((int) $productRow['id']);
                    } catch (InvalidArgumentException|RuntimeException $exception) {
                        $productToken = null;
                    }

                    $products[] = [
                        'token' => $productToken,
                        'vendor_product_token' => $vendorProductToken,
                        'product_name' => (string) $productRow['product_name'],
                        'brand' => (string) $productRow['brand'],
                        'country_of_origin' => (string) $productRow['country_of_origin'],
                        'product_category' => (string) $productRow['product_category'],
                        'product_size' => (string) $productRow['product_size'],
                        'unit' => (string) $productRow['unit'],
                        'rate' => (string) $productRow['rate'],
                        'rate_formatted' => number_format((float) $productRow['rate'], 2),
                        'item_weight' => (string) $productRow['item_weight'],
                        'dec_unit_price' => (string) $productRow['dec_unit_price'],
                        'dec_unit_price_formatted' => number_format((float) $productRow['dec_unit_price'], 2),
                        'asses_unit_price' => (string) $productRow['asses_unit_price'],
                        'asses_unit_price_formatted' => number_format((float) $productRow['asses_unit_price'], 2),
                        'hs_code' => (string) $productRow['hs_code'],
                        'quantity' => number_format((float) $productRow['quantity'], 3, '.', ''),
                        'quantity_formatted' => rtrim(rtrim(number_format((float) $productRow['quantity'], 3, '.', ''), '0'), '.') ?: '0',
                        'fob_total' => number_format((float) $productRow['fob_total'], 2, '.', ''),
                        'fob_total_formatted' => number_format((float) $productRow['fob_total'], 2),
                        'created_at' => (string) $productRow['created_at'],
                    ];
                }

                $proformas[] = [
                    'token' => $encodedId,
                    'invoice_number' => (string) $row['invoice_number'],
                    'freight_amount' => number_format((float) $row['freight_amount'], 2, '.', ''),
                    'freight_amount_formatted' => number_format((float) $row['freight_amount'], 2),
                    'created_at' => (string) $row['created_at'],
                    'created_at_human' => date('j M Y, g:i A', strtotime((string) $row['created_at'])),
                    'products' => $products,
                ];
            }

            $lcDetails = FileLcDetails::load($pdo, $fileId);
        }
    } catch (PDOException $exception) {
        $loadError = 'Unable to load the selected file right now. Please try again later.';
    }
}

$initialData = [
    'file' => $file ? [
        'id' => $file['id'] ?? null,
        'token' => $file['token'] ?? $fileToken,
        'file_name' => (string) $file['file_name'],
        'vendor_name' => (string) $file['vendor_name'],
        'vendor_id' => (int) $file['vendor_id'],
        'vendor_address' => (string) ($file['vendor_address'] ?? ''),
        'bank_name' => (string) $file['bank_name'],
        'brand' => (string) $file['brand'],
        'beneficiary_bank_name' => (string) ($file['beneficiary_bank_name'] ?? ''),
        'beneficiary_bank_address' => (string) ($file['beneficiary_bank_address'] ?? ''),
        'beneficiary_swift' => (string) ($file['beneficiary_swift'] ?? ''),
        'beneficiary_bank_account' => '',
        'advising_bank_name' => (string) ($file['advising_bank_name'] ?? ''),
        'advising_bank_account' => (string) ($file['advising_bank_account'] ?? ''),
        'advising_swift_code' => (string) ($file['advising_swift_code'] ?? ''),
        'bank_reference' => $bankReference,
        'bank_profile' => $bankProfile,
        'created_at' => $file['created_at'] ?? null,
        'created_at_human' => $file['created_at_human'] ?? '',
        'created_by' => $file['created_by'] ?? null,
        'created_by_name' => $file['created_by_name'] ?? null,
        'updated_at' => $file['updated_at'] ?? null,
        'updated_at_human' => $file['updated_at_human'] ?? null,
        'updated_by' => $file['updated_by'] ?? null,
        'updated_by_name' => $file['updated_by_name'] ?? null,
    ] : null,
    'vendorProducts' => array_values(array_filter(array_map(static function (array $product) {
        try {
            return [
                'token' => IdCipher::encode((int) $product['id']),
                'product_name' => (string) $product['product_name'],
                'brand' => (string) $product['brand'],
                'country_of_origin' => (string) $product['country_of_origin'],
                'product_category' => (string) $product['product_category'],
                'product_size' => (string) $product['product_size'],
                'unit' => (string) $product['unit'],
                'rate' => (string) $product['rate'],
                'item_weight' => (string) $product['item_weight'],
                'dec_unit_price' => (string) $product['dec_unit_price'],
                'asses_unit_price' => (string) $product['asses_unit_price'],
                'hs_code' => (string) $product['hs_code'],
            ];
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return null;
        }
    }, $vendorProducts))),
    'proformas' => $proformas,
    'lc' => $lcDetails,
    'bank' => $bankProfile,
];

try {
    $initialDataJson = json_encode($initialData, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    $initialDataJson = json_encode(['file' => null, 'vendorProducts' => [], 'proformas' => [], 'lc' => null]);
}

$createdSummary = '';
$updatedSummary = '';

if ($file !== null) {
    $createdSummary = trim('Created ' . ($file['created_at_human'] ?? ''));

    if (!empty($file['created_by_name'])) {
        $createdSummary = trim($createdSummary . ' by ' . $file['created_by_name']);
    }

    if (!empty($file['updated_at_human'])) {
        $updatedSummary = 'Last updated ' . $file['updated_at_human'];

        if (!empty($file['updated_by_name'])) {
            $updatedSummary .= ' by ' . $file['updated_by_name'];
        }
    } else {
        $updatedSummary = 'Not updated yet';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Proforma Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" id="fileInvoicesApp">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <a class="btn btn-link px-0" href="create.php">&#8592; Create Another File</a>
            <h1 class="h3 mb-1 mt-2">File Workspace</h1>
            <p class="text-muted mb-0">Capture proforma invoices, LC details, and attach product information for your shipment file.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/files/show.php'); ?>">Sign Out</a>
        </div>
    </div>

    <?php if ($loadError !== null): ?>
        <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
    <?php elseif ($file === null): ?>
        <div class="alert alert-warning" role="alert">We could not determine which file to load.</div>
    <?php else: ?>
        <?php include __DIR__ . '/partials/file_header.php'; ?>

        <div class="accordion" id="fileWorkspaceAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="proformaHeading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#proformaCollapse" aria-expanded="true" aria-controls="proformaCollapse">
                        Proforma Invoices
                    </button>
                </h2>
                <div id="proformaCollapse" class="accordion-collapse collapse show" data-bs-parent="#fileWorkspaceAccordion">
                    <div class="accordion-body">
                        <?php include __DIR__ . '/partials/proforma_section.php'; ?>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="lcHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lcCollapse" aria-expanded="false" aria-controls="lcCollapse">
                        Letter of Credit Details
                    </button>
                </h2>
                <div id="lcCollapse" class="accordion-collapse collapse" data-bs-parent="#fileWorkspaceAccordion">
                    <div class="accordion-body">
                        <?php include __DIR__ . '/partials/lc_section.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/partials/product_modal.php'; ?>
    <?php endif; ?>
</div>

<script id="fileInvoicesData" type="application/json"><?php echo htmlspecialchars($initialDataJson, ENT_NOQUOTES, 'UTF-8'); ?></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/file_invoices.js"></script>
</body>
</html>
