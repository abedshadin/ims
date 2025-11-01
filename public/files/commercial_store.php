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

$fileToken = trim($_POST['file_token'] ?? '');
$proformaToken = trim($_POST['proforma_token'] ?? '');
$invoiceNumber = trim($_POST['invoice_number'] ?? '');
$invoiceDateRaw = trim($_POST['invoice_date'] ?? '');

$fileId = $fileToken !== '' ? IdCipher::decode($fileToken) : null;
$proformaId = $proformaToken !== '' ? IdCipher::decode($proformaToken) : null;

if ($fileId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid file reference is required.',
    ]);
    exit;
}

if ($proformaId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid proforma invoice reference is required.',
    ]);
    exit;
}

if ($invoiceNumber === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'The commercial invoice number is required.',
    ]);
    exit;
}

$invoiceDate = null;

if ($invoiceDateRaw !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $invoiceDateRaw);
    if ($date instanceof DateTime) {
        $invoiceDate = $date->format('Y-m-d');
    }
}

if ($invoiceDate === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid invoice date is required (YYYY-MM-DD).',
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

    $proformaStatement = $pdo->prepare(
        'SELECT id, vendor_file_id, freight_amount FROM proforma_invoices WHERE id = :id LIMIT 1'
    );
    $proformaStatement->execute([':id' => $proformaId]);
    $proformaRow = $proformaStatement->fetch();

    if (!$proformaRow || (int) $proformaRow['vendor_file_id'] !== $fileId) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected proforma invoice could not be found for this file.',
        ]);
        exit;
    }

    $productsStatement = $pdo->prepare(
        'SELECT id, product_name, brand, country_of_origin, product_category, product_size, unit, hs_code, quantity, fob_total, item_weight
         FROM proforma_invoice_products
         WHERE proforma_invoice_id = :proforma_id
         ORDER BY id ASC'
    );
    $productsStatement->execute([':proforma_id' => $proformaId]);
    $proformaProducts = $productsStatement->fetchAll() ?: [];

    $freightAmount = isset($proformaRow['freight_amount']) ? (float) $proformaRow['freight_amount'] : 0.0;
    $totalWeight = 0.0;

    foreach ($proformaProducts as $productRow) {
        $quantity = (float) ($productRow['quantity'] ?? 0);
        $weightPerUnit = normaliseWeight($productRow['item_weight'] ?? null);
        $totalWeight += $quantity * $weightPerUnit;
    }

    $freightPerWeight = $totalWeight > 0 ? $freightAmount / $totalWeight : 0.0;

    $pdo->beginTransaction();

    $insertInvoice = $pdo->prepare(
        'INSERT INTO commercial_invoices (vendor_file_id, proforma_invoice_id, invoice_number, invoice_date, total_value, created_at, created_by)
         VALUES (:vendor_file_id, :proforma_invoice_id, :invoice_number, :invoice_date, :total_value, NOW(), :created_by)'
    );
    $insertInvoice->execute([
        ':vendor_file_id' => $fileId,
        ':proforma_invoice_id' => $proformaId,
        ':invoice_number' => $invoiceNumber,
        ':invoice_date' => $invoiceDate,
        ':total_value' => 0,
        ':created_by' => Auth::userId(),
    ]);

    $invoiceId = (int) $pdo->lastInsertId();

    $totalInvoiceValue = 0.0;

    if (!empty($proformaProducts)) {
        $insertProduct = $pdo->prepare(
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

        foreach ($proformaProducts as $productRow) {
            $quantity = max(0.0, (float) ($productRow['quantity'] ?? 0));
            $fobTotal = max(0.0, (float) ($productRow['fob_total'] ?? 0));
            $weightPerUnit = normaliseWeight($productRow['item_weight'] ?? null);
            $lineWeight = $quantity * $weightPerUnit;
            $unitFreightShare = $weightPerUnit * $freightPerWeight;
            $totalFreightShare = $unitFreightShare * $quantity;
            $unitFob = $quantity > 0 ? $fobTotal / $quantity : 0.0;
            $cnfPerUnit = $unitFob + $unitFreightShare;
            $cnfTotal = $cnfPerUnit * $quantity;

            $finalQuantity = number_format($quantity, 3, '.', '');
            $finalUnitPrice = number_format($unitFob, 2, '.', '');
            $totalItemPrice = number_format($fobTotal, 2, '.', '');
            $unitFreight = number_format($unitFreightShare, 4, '.', '');
            $totalFreight = number_format($totalFreightShare, 2, '.', '');
            $itemWeight = number_format($weightPerUnit, 3, '.', '');
            $totalWeightLine = number_format($lineWeight, 3, '.', '');
            $totalCnf = number_format($cnfTotal, 2, '.', '');
            $invoiceTotal = number_format($cnfTotal, 2, '.', '');

            $totalInvoiceValue += (float) $invoiceTotal;

            $insertProduct->execute([
                ':commercial_invoice_id' => $invoiceId,
                ':proforma_product_id' => (int) $productRow['id'],
                ':product_name' => (string) ($productRow['product_name'] ?? ''),
                ':brand' => (string) ($productRow['brand'] ?? ''),
                ':country_of_origin' => (string) ($productRow['country_of_origin'] ?? ''),
                ':product_category' => (string) ($productRow['product_category'] ?? ''),
                ':product_size' => (string) ($productRow['product_size'] ?? ''),
                ':unit' => (string) ($productRow['unit'] ?? ''),
                ':hs_code' => (string) ($productRow['hs_code'] ?? ''),
                ':final_quantity' => $finalQuantity,
                ':final_unit_price' => $finalUnitPrice,
                ':total_item_price' => $totalItemPrice,
                ':unit_freight' => $unitFreight,
                ':total_freight' => $totalFreight,
                ':item_weight' => $itemWeight,
                ':total_weight' => $totalWeightLine,
                ':total_cnf_value' => $totalCnf,
                ':invoice_total' => $invoiceTotal,
            ]);
        }
    }

    $updateInvoice = $pdo->prepare(
        'UPDATE commercial_invoices SET total_value = :total_value WHERE id = :id'
    );
    $updateInvoice->execute([
        ':total_value' => number_format($totalInvoiceValue, 2, '.', ''),
        ':id' => $invoiceId,
    ]);

    $updateFile = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFile->execute([
        ':updated_by' => Auth::userId(),
        ':id' => $fileId,
    ]);

    $pdo->commit();

    $invoice = CommercialInvoice::loadById($pdo, $invoiceId);
    $fileMeta = FileMetadata::load($pdo, $fileId);

    echo json_encode([
        'status' => 'success',
        'message' => 'Commercial invoice created.',
        'invoice' => $invoice,
        'file_meta' => $fileMeta,
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create the commercial invoice right now.',
    ]);
}
