<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';
require_once __DIR__ . '/../../app/CommercialInvoice.php';

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

$ciToken = trim($_POST['ci_token'] ?? '');
$piToken = trim($_POST['pi_token'] ?? '');
$productMode = $_POST['product_mode'] ?? 'existing';
$vendorProductToken = trim($_POST['vendor_product_id'] ?? '');
$quantityRaw = trim($_POST['quantity'] ?? '');
$fobTotalRaw = trim($_POST['fob_total'] ?? '');

$ciId = $ciToken !== '' ? IdCipher::decode($ciToken) : null;

if ($ciId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid commercial invoice reference is required.',
    ]);
    exit;
}

function normaliseWeight(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $sanitised = preg_replace('/[^0-9.+\-]/', '', (string) $value);

    if ($sanitised === '' || !is_numeric($sanitised)) {
        return 0.0;
    }

    return (float) $sanitised;
}

try {
    $pdo = Database::getConnection();

    $invoiceStatement = $pdo->prepare(
        'SELECT
            ci.id,
            ci.vendor_file_id,
            ci.proforma_invoice_id,
            pi.freight_amount,
            vf.vendor_id
         FROM commercial_invoices ci
         INNER JOIN vendor_files vf ON vf.id = ci.vendor_file_id
         INNER JOIN proforma_invoices pi ON pi.id = ci.proforma_invoice_id
         WHERE ci.id = :id
         LIMIT 1'
    );
    $invoiceStatement->execute([':id' => $ciId]);
    $invoice = $invoiceStatement->fetch();

    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected commercial invoice could not be found.',
        ]);
        exit;
    }

    $proformaId = (int) $invoice['proforma_invoice_id'];
    $vendorFileId = (int) $invoice['vendor_file_id'];
    $vendorId = (int) $invoice['vendor_id'];

    if ($piToken !== '') {
        $piId = IdCipher::decode($piToken);
        if ($piId === null || $piId !== $proformaId) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => 'The linked proforma invoice reference is invalid.',
            ]);
            exit;
        }
    }

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

    $insertProformaProduct = $pdo->prepare(
        'INSERT INTO proforma_invoice_products (
            proforma_invoice_id, vendor_product_id, product_name, brand, country_of_origin, product_category, product_size, unit, rate, item_weight, dec_unit_price, asses_unit_price, hs_code, quantity, fob_total, created_at
        ) VALUES (
            :proforma_invoice_id, :vendor_product_id, :product_name, :brand, :country_of_origin, :product_category, :product_size, :unit, :rate, :item_weight, :dec_unit_price, :asses_unit_price, :hs_code, :quantity, :fob_total, NOW()
        )'
    );

    $insertProformaProduct->execute([
        ':proforma_invoice_id' => $proformaId,
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

    $proformaProductId = (int) $pdo->lastInsertId();

    $freightAmount = isset($invoice['freight_amount']) ? (float) $invoice['freight_amount'] : 0.0;

    $productsStatement = $pdo->prepare(
        'SELECT quantity, item_weight FROM proforma_invoice_products WHERE proforma_invoice_id = :proforma_id'
    );
    $productsStatement->execute([':proforma_id' => $proformaId]);

    $totalWeight = 0.0;
    while ($row = $productsStatement->fetch()) {
        $rowQuantity = (float) ($row['quantity'] ?? 0);
        $rowWeight = normaliseWeight($row['item_weight'] ?? null);
        $totalWeight += $rowQuantity * $rowWeight;
    }

    $freightPerWeight = $totalWeight > 0 ? $freightAmount / $totalWeight : 0.0;
    $unitWeight = normaliseWeight($productData['item_weight']);
    $lineWeight = $unitWeight * $quantity;
    $unitFreightShare = $unitWeight * $freightPerWeight;
    $totalFreightShare = $unitFreightShare * $quantity;
    $unitFob = $quantity > 0 ? $fobTotal / $quantity : 0.0;
    $cnfPerUnit = $unitFob + $unitFreightShare;
    $cnfTotal = $cnfPerUnit * $quantity;

    $insertCommercialProduct = $pdo->prepare(
        'INSERT INTO commercial_invoice_products (
            commercial_invoice_id,
            proforma_invoice_product_id,
            product_name,
            brand,
            country_of_origin,
            product_category,
            product_size,
            unit,
            hs_code,
            final_quantity,
            final_unit_price,
            total_item_price,
            unit_freight,
            total_freight,
            item_weight,
            total_weight,
            total_cnf_value,
            invoice_total,
            created_at
        ) VALUES (
            :commercial_invoice_id,
            :proforma_product_id,
            :product_name,
            :brand,
            :country_of_origin,
            :product_category,
            :product_size,
            :unit,
            :hs_code,
            :final_quantity,
            :final_unit_price,
            :total_item_price,
            :unit_freight,
            :total_freight,
            :item_weight,
            :total_weight,
            :total_cnf_value,
            :invoice_total,
            NOW()
        )'
    );

    $insertCommercialProduct->execute([
        ':commercial_invoice_id' => $ciId,
        ':proforma_product_id' => $proformaProductId,
        ':product_name' => $productData['product_name'],
        ':brand' => $productData['brand'],
        ':country_of_origin' => $productData['country_of_origin'],
        ':product_category' => $productData['product_category'],
        ':product_size' => $productData['product_size'],
        ':unit' => $productData['unit'],
        ':hs_code' => $productData['hs_code'],
        ':final_quantity' => number_format($quantity, 3, '.', ''),
        ':final_unit_price' => number_format($unitFob, 2, '.', ''),
        ':total_item_price' => number_format($fobTotal, 2, '.', ''),
        ':unit_freight' => number_format($unitFreightShare, 4, '.', ''),
        ':total_freight' => number_format($totalFreightShare, 2, '.', ''),
        ':item_weight' => number_format($unitWeight, 3, '.', ''),
        ':total_weight' => number_format($lineWeight, 3, '.', ''),
        ':total_cnf_value' => number_format($cnfPerUnit, 2, '.', ''),
        ':invoice_total' => number_format($cnfTotal, 2, '.', ''),
    ]);

    $totalValueStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(invoice_total), 0) AS total FROM commercial_invoice_products WHERE commercial_invoice_id = :invoice_id'
    );
    $totalValueStatement->execute([':invoice_id' => $ciId]);
    $totalInvoiceValue = (float) ($totalValueStatement->fetchColumn() ?? 0);

    $updateInvoice = $pdo->prepare(
        'UPDATE commercial_invoices
         SET total_value = :total_value,
             updated_at = NOW(),
             updated_by = :updated_by
         WHERE id = :id'
    );
    $updateInvoice->execute([
        ':total_value' => number_format($totalInvoiceValue, 2, '.', ''),
        ':updated_by' => Auth::userId(),
        ':id' => $ciId,
    ]);

    $updateFileStatement = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFileStatement->execute([
        ':updated_by' => Auth::userId(),
        ':id' => $vendorFileId,
    ]);

    $pdo->commit();

    try {
        $proformaProductToken = IdCipher::encode($proformaProductId);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $proformaProductToken = null;
    }

    $fileMeta = FileMetadata::load($pdo, $vendorFileId);
    $invoiceData = CommercialInvoice::loadById($pdo, $ciId);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product added to the commercial invoice.',
        'product' => [
            'token' => $proformaProductToken,
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
        'invoice' => $invoiceData,
        'new_vendor_product' => $newVendorProduct,
        'file_meta' => $fileMeta,
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to add the product right now.',
    ]);
}
