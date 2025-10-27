<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';

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

if ($fileId === null) {
    $loadError = 'A valid file reference was not provided.';
} else {
    try {
        $pdo = Database::getConnection();

        $fileStatement = $pdo->prepare(
            'SELECT vf.id, vf.file_name, vf.bank_name, vf.brand, vf.created_at, vf.vendor_id, v.vendor_name
             FROM vendor_files vf
             INNER JOIN vendors v ON v.id = vf.vendor_id
             WHERE vf.id = :id'
        );
        $fileStatement->execute([':id' => $fileId]);
        $file = $fileStatement->fetch();

        if (!$file) {
            $loadError = 'The requested file could not be found.';
        } else {
            $vendorProductsStatement = $pdo->prepare(
                'SELECT id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code
                 FROM vendor_products
                 WHERE vendor_id = :vendor_id
                 ORDER BY product_name ASC'
            );
            $vendorProductsStatement->execute([':vendor_id' => $file['vendor_id']]);
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
        }
    } catch (PDOException $exception) {
        $loadError = 'Unable to load the selected file right now. Please try again later.';
    }
}

$initialData = [
    'file' => $file ? [
        'token' => $fileToken,
        'file_name' => (string) $file['file_name'],
        'vendor_name' => (string) $file['vendor_name'],
        'bank_name' => (string) $file['bank_name'],
        'brand' => (string) $file['brand'],
        'created_at_human' => $file ? date('j M Y, g:i A', strtotime((string) $file['created_at'])) : '',
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
];

try {
    $initialDataJson = json_encode($initialData, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    $initialDataJson = json_encode(['file' => null, 'vendorProducts' => [], 'proformas' => []]);
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
            <p class="text-muted mb-0">Capture proforma invoices and attach product details for your shipment file.</p>
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
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <span class="badge text-bg-primary-subtle text-primary rounded-pill px-3 py-2">Reference <?php echo e($file['file_name']); ?></span>
                        <h2 class="h4 mt-3 mb-1"><?php echo e($file['vendor_name']); ?></h2>
                        <p class="text-muted mb-0">Bank: <span class="fw-semibold"><?php echo e($file['bank_name']); ?></span> &middot; Brand: <span class="fw-semibold"><?php echo e($file['brand']); ?></span></p>
                    </div>
                    <div class="text-lg-end text-muted">
                        <div class="small">Created <?php echo e(date('j M Y, g:i A', strtotime((string) $file['created_at']))); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="h5 mb-1">Add a Proforma Invoice</h2>
                        <p class="text-muted mb-0">Create a proforma invoice record and then attach the required products below.</p>
                    </div>
                </div>
                <form id="createPiForm" class="row gy-3 mt-3" method="post" novalidate>
                    <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
                    <div class="col-lg-5">
                        <label class="form-label text-uppercase small fw-semibold" for="invoice_number">Proforma Invoice Number</label>
                        <input class="form-control form-control-lg" type="text" id="invoice_number" name="invoice_number" placeholder="e.g. PI-2025-001" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label text-uppercase small fw-semibold" for="freight_amount">Freight Amount</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input class="form-control" type="number" step="0.01" id="freight_amount" name="freight_amount" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-lg-4 d-flex align-items-end justify-content-lg-end">
                        <button class="btn btn-primary btn-lg w-100 w-lg-auto" type="submit">Add Proforma Invoice</button>
                    </div>
                </form>
                <div id="piAlert" class="alert d-none mt-3" role="alert"></div>
            </div>
        </div>

        <div id="piList" class="mb-4"></div>
        <div id="noPiMessage" class="text-center text-muted py-5<?php echo empty($proformas) ? '' : ' d-none'; ?>">
            <div class="mb-2">
                <span class="display-6 d-block">📄</span>
            </div>
            <p class="lead mb-1">No proforma invoices yet</p>
            <p class="text-muted mb-0">Add your first proforma invoice to begin attaching vendor products.</p>
        </div>

        <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Products to Proforma Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="productForm" method="post" novalidate>
                            <input type="hidden" name="pi_token" id="pi_token" value="">
                            <div class="mb-4">
                                <span class="text-uppercase small fw-semibold text-muted">Product Source</span>
                                <div class="d-flex flex-wrap gap-3 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="product_mode" id="mode_existing" value="existing" checked>
                                        <label class="form-check-label" for="mode_existing">Use saved vendor product</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="product_mode" id="mode_new" value="new">
                                        <label class="form-check-label" for="mode_new">Create a new product</label>
                                    </div>
                                </div>
                            </div>

                            <div id="existingProductFields" class="mb-4">
                                <label class="form-label text-uppercase small fw-semibold" for="vendor_product_id">Vendor Products</label>
                                <select class="form-select" id="vendor_product_id" name="vendor_product_id">
                                    <option value="">Select a product</option>
                                </select>
                                <div id="vendorProductPreview" class="mt-3 d-none">
                                    <div class="bg-body-secondary rounded p-3">
                                        <div class="fw-semibold" data-preview="product_name"></div>
                                        <div class="small text-muted" data-preview="brand"></div>
                                        <div class="row row-cols-1 row-cols-md-2 g-2 mt-2">
                                            <div class="col"><span class="text-muted small">Category</span><div data-preview="product_category" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">COO</span><div data-preview="country_of_origin" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">Size</span><div data-preview="product_size" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">Unit</span><div data-preview="unit" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">Unit Rate</span><div data-preview="rate" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">Item Weight</span><div data-preview="item_weight" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">DEC Unit Price</span><div data-preview="dec_unit_price" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">ASSES Unit Price</span><div data-preview="asses_unit_price" class="fw-semibold"></div></div>
                                            <div class="col"><span class="text-muted small">HS Code</span><div data-preview="hs_code" class="fw-semibold"></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="newProductFields" class="d-none">
                                <div class="alert alert-info" role="alert">
                                    This product will be saved to the vendor catalogue and linked to the proforma invoice.
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="product_name">Product Name</label>
                                        <input class="form-control" type="text" id="product_name" name="product_name" placeholder="Product name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="brand">Brand</label>
                                        <select class="form-select" id="brand" name="brand">
                                            <option value="">Select brand</option>
                                            <option value="PH">PH</option>
                                            <option value="KFC">KFC</option>
                                            <option value="PH/KFC">PH/KFC</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="country_of_origin">COO</label>
                                        <input class="form-control" type="text" id="country_of_origin" name="country_of_origin" placeholder="e.g. Malaysia">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="product_category">Product Category</label>
                                        <select class="form-select" id="product_category" name="product_category">
                                            <option value="">Select category</option>
                                            <option value="RM">Raw Material (RM)</option>
                                            <option value="EQ">Equipment (EQ)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="product_size">Size</label>
                                        <select class="form-select" id="product_size" name="product_size">
                                            <option value="">Select size</option>
                                            <option value="Carton">Carton</option>
                                            <option value="Case">Case</option>
                                            <option value="MTN">MTN</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="unit">Unit</label>
                                        <input class="form-control" type="text" id="unit" name="unit" placeholder="e.g. pcs, kg">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="rate">Unit Rate</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input class="form-control" type="number" step="0.01" id="rate" name="rate" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="item_weight">Item Weight</label>
                                        <input class="form-control" type="text" id="item_weight" name="item_weight" placeholder="e.g. 2.5 kg">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="dec_unit_price">Dec Unit Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input class="form-control" type="number" step="0.01" id="dec_unit_price" name="dec_unit_price" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="asses_unit_price">Asses Unit Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input class="form-control" type="number" step="0.01" id="asses_unit_price" name="asses_unit_price" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="hs_code">HS Code</label>
                                        <input class="form-control" type="text" id="hs_code" name="hs_code" placeholder="Customs classification">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="quantity">Quantity</label>
                                    <input class="form-control" type="number" step="0.001" min="0.001" id="quantity" name="quantity" placeholder="e.g. 100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="fob_total">FOB Total</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input class="form-control" type="number" step="0.01" min="0" id="fob_total" name="fob_total" placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted small mt-1">Quantity and FOB totals feed the C&amp;F calculation for this invoice.</p>

                            <div id="productFormAlert" class="alert d-none mt-4" role="alert"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="productFormSubmit">Add Product</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script id="fileInvoicesData" type="application/json"><?php echo htmlspecialchars($initialDataJson, ENT_NOQUOTES, 'UTF-8'); ?></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/file_invoices.js"></script>
</body>
</html>
