<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$piToken = trim($_POST['pi_token'] ?? '');
$productMode = $_POST['product_mode'] ?? 'existing';
$vendorProductToken = trim($_POST['vendor_product_id'] ?? '');
$quantityRaw = trim($_POST['quantity'] ?? '');
$fobTotalRaw = trim($_POST['fob_total'] ?? '');

if ($piToken === '' || ($piId = IdCipher::decode($piToken)) === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid proforma invoice reference is required.',
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();

    $invoiceStatement = $pdo->prepare(
        'SELECT pi.id, pi.vendor_file_id, vf.vendor_id
         FROM proforma_invoices pi
         INNER JOIN vendor_files vf ON vf.id = pi.vendor_file_id
         WHERE pi.id = :id'
    );
    $invoiceStatement->execute([':id' => $piId]);
    $invoice = $invoiceStatement->fetch();

    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected proforma invoice could not be found.',
        ]);
        exit;
    }

    $vendorId = (int) $invoice['vendor_id'];

    if ($quantityRaw === '' || $fobTotalRaw === '') {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Quantity and FOB total are required for each product.',
        ]);
        exit;
    }

    if (!is_numeric($quantityRaw) || !is_numeric($fobTotalRaw)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Quantity and FOB total must be numeric values.',
        ]);
        exit;
    }

    $quantity = (float) $quantityRaw;
    $fobTotal = (float) $fobTotalRaw;

    if ($quantity <= 0) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Quantity must be greater than zero.',
        ]);
        exit;
    }

    if ($fobTotal < 0) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'FOB total cannot be negative.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $productData = [
        'product_name' => null,
        'brand' => null,
        'country_of_origin' => null,
        'product_category' => null,
        'product_size' => null,
        'unit' => null,
        'rate' => null,
        'item_weight' => null,
        'dec_unit_price' => null,
        'asses_unit_price' => null,
        'hs_code' => null,
    ];

    $vendorProductId = null;
    $newVendorProduct = null;

    if ($productMode === 'existing') {
        if ($vendorProductToken === '' || ($vendorProductId = IdCipher::decode($vendorProductToken)) === null) {
            $pdo->rollBack();

            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => 'Please select a vendor product to attach.',
            ]);
            exit;
        }

        $productStatement = $pdo->prepare(
            'SELECT id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code
             FROM vendor_products
             WHERE id = :id AND vendor_id = :vendor_id'
        );
        $productStatement->execute([
            ':id' => $vendorProductId,
            ':vendor_id' => $vendorId,
        ]);
        $existingProduct = $productStatement->fetch();

        if (!$existingProduct) {
            $pdo->rollBack();

            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'The selected vendor product could not be found.',
            ]);
            exit;
        }

        foreach ($productData as $key => $_) {
            $productData[$key] = (string) $existingProduct[$key];
        }
    } else {
        $requiredFields = ['product_name', 'brand', 'country_of_origin', 'product_category', 'product_size', 'unit', 'rate', 'item_weight', 'dec_unit_price', 'asses_unit_price', 'hs_code'];

        foreach ($requiredFields as $field) {
            $value = trim($_POST[$field] ?? '');

            if ($value === '') {
                $pdo->rollBack();

                http_response_code(422);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'All product details are required when creating a new item.',
                ]);
                exit;
            }

            $productData[$field] = $value;
        }

        foreach (['rate', 'dec_unit_price', 'asses_unit_price'] as $numericField) {
            if (!is_numeric($productData[$numericField])) {
                $pdo->rollBack();

                http_response_code(422);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Numeric values are required for pricing fields.',
                ]);
                exit;
            }
        }

        $insertProductStatement = $pdo->prepare(
            'INSERT INTO vendor_products (
                vendor_id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code, created_at
            ) VALUES (
                :vendor_id, :product_name, :brand, :country_of_origin, :product_category, :product_size, :unit, :rate, :item_weight, :dec_unit_price, :asses_unit_price, :hs_code, NOW()
            )'
        );

        $insertProductStatement->execute([
            ':vendor_id' => $vendorId,
            ':product_name' => $productData['product_name'],
            ':brand' => $productData['brand'],
            ':country_of_origin' => $productData['country_of_origin'],
            ':product_category' => $productData['product_category'],
            ':product_size' => $productData['product_size'],
            ':unit' => $productData['unit'],
            ':rate' => $productData['rate'],
            ':item_weight' => $productData['item_weight'],
            ':dec_unit_price' => $productData['dec_unit_price'],
            ':asses_unit_price' => $productData['asses_unit_price'],
            ':hs_code' => $productData['hs_code'],
        ]);

        $vendorProductId = (int) $pdo->lastInsertId();

        try {
            $vendorProductToken = IdCipher::encode($vendorProductId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $vendorProductToken = null;
        }

        $newVendorProduct = [
            'token' => $vendorProductToken,
            'product_name' => $productData['product_name'],
            'brand' => $productData['brand'],
            'country_of_origin' => $productData['country_of_origin'],
            'product_category' => $productData['product_category'],
            'product_size' => $productData['product_size'],
            'unit' => $productData['unit'],
            'rate' => $productData['rate'],
            'item_weight' => $productData['item_weight'],
            'dec_unit_price' => $productData['dec_unit_price'],
            'asses_unit_price' => $productData['asses_unit_price'],
            'hs_code' => $productData['hs_code'],
        ];
    }

    $insertInvoiceProduct = $pdo->prepare(
        'INSERT INTO proforma_invoice_products (
            proforma_invoice_id, vendor_product_id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code, quantity, fob_total, created_at
        ) VALUES (
            :proforma_invoice_id, :vendor_product_id, :product_name, :brand, :country_of_origin, :product_category, :product_size, :unit, :rate, :item_weight, :dec_unit_price, :asses_unit_price, :hs_code, :quantity, :fob_total, NOW()
        )'
    );

    $insertInvoiceProduct->execute([
        ':proforma_invoice_id' => $piId,
        ':vendor_product_id' => $vendorProductId,
        ':product_name' => $productData['product_name'],
        ':brand' => $productData['brand'],
        ':country_of_origin' => $productData['country_of_origin'],
        ':product_category' => $productData['product_category'],
        ':product_size' => $productData['product_size'],
        ':unit' => $productData['unit'],
        ':rate' => $productData['rate'],
        ':item_weight' => $productData['item_weight'],
        ':dec_unit_price' => $productData['dec_unit_price'],
        ':asses_unit_price' => $productData['asses_unit_price'],
        ':hs_code' => $productData['hs_code'],
        ':quantity' => $quantity,
        ':fob_total' => $fobTotal,
    ]);

    $invoiceProductId = (int) $pdo->lastInsertId();

    $updateFileStatement = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFileStatement->execute([
        ':updated_by' => Auth::userId(),
        ':id' => (int) $invoice['vendor_file_id'],
    ]);

    $pdo->commit();

    try {
        $invoiceProductToken = IdCipher::encode($invoiceProductId);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $invoiceProductToken = null;
    }

    $fileMeta = FileMetadata::load($pdo, (int) $invoice['vendor_file_id']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product added to the proforma invoice.',
        'product' => [
            'token' => $invoiceProductToken,
            'vendor_product_token' => $vendorProductToken,
            'product_name' => $productData['product_name'],
            'brand' => $productData['brand'],
            'country_of_origin' => $productData['country_of_origin'],
            'product_category' => $productData['product_category'],
            'product_size' => $productData['product_size'],
            'unit' => $productData['unit'],
            'rate' => $productData['rate'],
            'rate_formatted' => number_format((float) $productData['rate'], 2),
            'item_weight' => $productData['item_weight'],
            'dec_unit_price' => $productData['dec_unit_price'],
            'dec_unit_price_formatted' => number_format((float) $productData['dec_unit_price'], 2),
            'asses_unit_price' => $productData['asses_unit_price'],
            'asses_unit_price_formatted' => number_format((float) $productData['asses_unit_price'], 2),
            'hs_code' => $productData['hs_code'],
            'quantity' => number_format($quantity, 3, '.', ''),
            'quantity_formatted' => rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') ?: '0',
            'fob_total' => number_format($fobTotal, 2, '.', ''),
            'fob_total_formatted' => number_format($fobTotal, 2),
        ],
        'new_vendor_product' => $newVendorProduct,
        'file_meta' => $fileMeta,
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to add the product right now.',
    ]);
}
