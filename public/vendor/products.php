<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';

Auth::requireLogin('/auth/login.php');

$currentUserName = Auth::userName();
$vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$loadError = null;
$vendor = null;
$products = [];
$productToEdit = null;
$isEditing = false;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($vendorId <= 0) {
    $loadError = 'A valid vendor was not specified.';
} else {
    try {
        $pdo = Database::getConnection();
        $vendorStatement = $pdo->prepare('SELECT id, vendor_name FROM vendors WHERE id = :id');
        $vendorStatement->execute([':id' => $vendorId]);
        $vendor = $vendorStatement->fetch();

        if (!$vendor) {
            $loadError = 'The requested vendor could not be found.';
        } else {
            $productsStatement = $pdo->prepare(
                'SELECT id, product_name, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code, created_at, updated_at
                 FROM vendor_products
                 WHERE vendor_id = :vendor_id
                 ORDER BY created_at DESC'
            );
            $productsStatement->execute([':vendor_id' => $vendorId]);
            $products = $productsStatement->fetchAll();

            if ($productId > 0) {
                $editStatement = $pdo->prepare(
                    'SELECT id, product_name, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code
                     FROM vendor_products
                     WHERE id = :id AND vendor_id = :vendor_id'
                );
                $editStatement->execute([
                    ':id' => $productId,
                    ':vendor_id' => $vendorId,
                ]);
                $productToEdit = $editStatement->fetch();
                $isEditing = $productToEdit !== false && $productToEdit !== null;
            }
        }
    } catch (\PDOException $exception) {
        $loadError = 'Unable to load vendor products right now. Please try again later.';
    }
}

$defaultProductValues = [
    'product_name' => '',
    'product_size' => '',
    'unit' => '',
    'rate' => '',
    'item_weight' => '',
    'dec_unit_price' => '',
    'asses_unit_price' => '',
    'hs_code' => '',
];

if (!$isEditing) {
    $productToEdit = $defaultProductValues;
} else {
    $productToEdit = array_merge($defaultProductValues, $productToEdit);
}

$formEndpoint = $isEditing ? 'products_update.php' : 'products_store.php';
$formSubmitLabel = $isEditing ? 'Update Product' : 'Add Product';
$formResetSetting = $isEditing ? 'false' : 'true';
$redirectTarget = 'products.php?vendor_id=' . urlencode((string) $vendorId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Vendor Products</h1>
            <p class="text-muted mb-0">Capture product details for vendor quotations and documentation.</p>
        </div>
        <div class="text-end">
            <?php if ($currentUserName): ?>
                <div class="fw-semibold">Signed in as <?php echo e($currentUserName); ?></div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="../auth/logout.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/vendor/products.php'); ?>">Sign Out</a>
        </div>
    </div>

    <?php if ($loadError !== null): ?>
        <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
    <?php else: ?>
        <div class="mb-4">
            <a class="btn btn-link px-0" href="index.php">&#8592; Back to Vendor List</a>
            <h2 class="h5 mt-3 mb-0">Managing products for: <span class="fw-semibold"><?php echo e($vendor['vendor_name']); ?></span></h2>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php echo $isEditing ? 'Edit Product' : 'Add a New Product'; ?></h2>
                <form id="productForm" method="post" data-endpoint="<?php echo e($formEndpoint); ?>" data-reset-on-success="<?php echo e($formResetSetting); ?>" data-redirect="<?php echo e($redirectTarget); ?>" novalidate>
                    <input type="hidden" name="vendor_id" value="<?php echo e((string) $vendorId); ?>">
                    <?php if ($isEditing && isset($productToEdit['id'])): ?>
                        <input type="hidden" name="product_id" value="<?php echo e((string) $productToEdit['id']); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="product_name">Product Name</label>
                            <input class="form-control" type="text" id="product_name" name="product_name" value="<?php echo e($productToEdit['product_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="product_size">Size</label>
                            <input class="form-control" type="text" id="product_size" name="product_size" value="<?php echo e($productToEdit['product_size']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="unit">Unit</label>
                            <input class="form-control" type="text" id="unit" name="unit" value="<?php echo e($productToEdit['unit']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="rate">Rate</label>
                            <input class="form-control" type="number" step="0.01" id="rate" name="rate" value="<?php echo e($productToEdit['rate']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="item_weight">Item Weight</label>
                            <input class="form-control" type="text" id="item_weight" name="item_weight" value="<?php echo e($productToEdit['item_weight']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dec_unit_price">Dec Unit Price</label>
                            <input class="form-control" type="number" step="0.01" id="dec_unit_price" name="dec_unit_price" value="<?php echo e($productToEdit['dec_unit_price']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="asses_unit_price">Asses Unit Price</label>
                            <input class="form-control" type="number" step="0.01" id="asses_unit_price" name="asses_unit_price" value="<?php echo e($productToEdit['asses_unit_price']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="hs_code">HS Code</label>
                            <input class="form-control" type="text" id="hs_code" name="hs_code" value="<?php echo e($productToEdit['hs_code']); ?>" required>
                        </div>
                    </div>
                    <div id="productFormAlert" class="alert d-none" role="alert"></div>
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?php echo e($formSubmitLabel); ?></button>
                        <?php if ($isEditing): ?>
                            <a class="btn btn-outline-secondary" href="products.php?vendor_id=<?php echo urlencode((string) $vendorId); ?>">Cancel Editing</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="reset">Clear</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Existing Products</h2>
                <?php if (empty($products)): ?>
                    <p class="text-muted mb-0">No products have been added for this vendor yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Unit</th>
                                    <th scope="col">Rate</th>
                                    <th scope="col">Item Weight</th>
                                    <th scope="col">Dec Unit Price</th>
                                    <th scope="col">Asses Unit Price</th>
                                    <th scope="col">HS Code</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo e($product['product_name']); ?></td>
                                        <td><?php echo e($product['product_size']); ?></td>
                                        <td><?php echo e($product['unit']); ?></td>
                                        <td><?php echo e(number_format((float) $product['rate'], 2)); ?></td>
                                        <td><?php echo e($product['item_weight']); ?></td>
                                        <td><?php echo e(number_format((float) $product['dec_unit_price'], 2)); ?></td>
                                        <td><?php echo e(number_format((float) $product['asses_unit_price'], 2)); ?></td>
                                        <td><?php echo e($product['hs_code']); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="products.php?vendor_id=<?php echo urlencode((string) $vendorId); ?>&product_id=<?php echo urlencode((string) $product['id']); ?>">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <a class="btn btn-link" href="../dashboard.php">&#8592; Back to Dashboard</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/vendor_products.js"></script>
</body>
</html>
