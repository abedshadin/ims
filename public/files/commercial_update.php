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

$invoiceToken = trim($_POST['ci_token'] ?? '');
$invoiceNumber = trim($_POST['invoice_number'] ?? '');
$invoiceDateRaw = trim($_POST['invoice_date'] ?? '');
$productInputs = $_POST['products'] ?? [];

$invoiceId = $invoiceToken !== '' ? IdCipher::decode($invoiceToken) : null;

if ($invoiceId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid commercial invoice reference is required.',
    ]);
    exit;
}

if ($invoiceNumber === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'The commercial invoice number is required.',
        'errors' => ['invoice_number' => 'required'],
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
        'errors' => ['invoice_date' => 'invalid'],
    ]);
    exit;
}

function normaliseDecimalInput(mixed $value, int $scale): string
{
    if ($value === null) {
        $value = '0';
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            $value = '0';
        }
    }

    if (!is_numeric($value)) {
        $sanitised = preg_replace('/[^0-9.+\-]/', '', (string) $value);
        if ($sanitised === '' || !is_numeric($sanitised)) {
            throw new InvalidArgumentException('invalid');
        }
        $value = $sanitised;
    }

    $number = (float) $value;

    return number_format($number, $scale, '.', '');
}

function extractDecimalField(array $fields, string $key, int $scale, string $label, string $fieldIdentifier): string
{
    try {
        return normaliseDecimalInput($fields[$key] ?? '0', $scale);
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => sprintf('%s must be a numeric value.', $label),
            'errors' => [
                $fieldIdentifier => 'invalid',
            ],
        ]);
        exit;
    }
}

try {
    $pdo = Database::getConnection();

    $invoiceStatement = $pdo->prepare(
        'SELECT id, vendor_file_id, total_value FROM commercial_invoices WHERE id = :id LIMIT 1'
    );
    $invoiceStatement->execute([':id' => $invoiceId]);
    $invoiceRow = $invoiceStatement->fetch();

    if (!$invoiceRow) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected commercial invoice could not be found.',
        ]);
        exit;
    }

    $fileId = (int) $invoiceRow['vendor_file_id'];
    $currentTotalValue = isset($invoiceRow['total_value']) ? (float) $invoiceRow['total_value'] : 0.0;

    $existingProductsStatement = $pdo->prepare(
        'SELECT id FROM commercial_invoice_products WHERE commercial_invoice_id = :invoice_id'
    );
    $existingProductsStatement->execute([':invoice_id' => $invoiceId]);
    $existingProducts = [];

    while ($row = $existingProductsStatement->fetch()) {
        $existingProducts[(int) $row['id']] = true;
    }

    $hasProductInput = is_array($productInputs) && !empty($productInputs);
    $invoiceTotalSum = 0.0;

    $pdo->beginTransaction();

    if ($hasProductInput) {
        $updateProduct = $pdo->prepare(
            'UPDATE commercial_invoice_products
             SET final_quantity = :final_quantity,
                 final_unit_price = :final_unit_price,
                 total_item_price = :total_item_price,
                 unit_freight = :unit_freight,
                 total_freight = :total_freight,
                 item_weight = :item_weight,
                 total_weight = :total_weight,
                 total_cnf_value = :total_cnf_value,
                 invoice_total = :invoice_total,
                 updated_at = NOW()
             WHERE id = :id AND commercial_invoice_id = :invoice_id'
        );

        foreach ($productInputs as $productToken => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $productId = IdCipher::decode((string) $productToken);

            if ($productId === null || !isset($existingProducts[$productId])) {
                http_response_code(422);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'One or more product references are invalid.',
                ]);
                exit;
            }

            $fieldPrefix = sprintf('products[%s]', (string) $productToken);

            $finalQuantity = extractDecimalField($fields, 'final_quantity', 3, 'Final quantity', $fieldPrefix . '[final_quantity]');
            $finalUnitPrice = extractDecimalField($fields, 'final_unit_price', 2, 'Final unit price', $fieldPrefix . '[final_unit_price]');
            $totalItemPrice = extractDecimalField($fields, 'total_item_price', 2, 'Total item price', $fieldPrefix . '[total_item_price]');
            $unitFreight = extractDecimalField($fields, 'unit_freight', 4, 'Unit freight', $fieldPrefix . '[unit_freight]');
            $totalFreight = extractDecimalField($fields, 'total_freight', 2, 'Total freight', $fieldPrefix . '[total_freight]');
            $itemWeight = extractDecimalField($fields, 'item_weight', 3, 'Item weight', $fieldPrefix . '[item_weight]');
            $totalWeight = extractDecimalField($fields, 'total_weight', 3, 'Total weight', $fieldPrefix . '[total_weight]');
            $totalCnfValue = extractDecimalField($fields, 'total_cnf_value', 2, 'Total C&F value', $fieldPrefix . '[total_cnf_value]');
            $invoiceTotal = extractDecimalField($fields, 'invoice_total', 2, 'Total commercial invoice value', $fieldPrefix . '[invoice_total]');

            $invoiceTotalSum += (float) $invoiceTotal;

            $updateProduct->execute([
                ':final_quantity' => $finalQuantity,
                ':final_unit_price' => $finalUnitPrice,
                ':total_item_price' => $totalItemPrice,
                ':unit_freight' => $unitFreight,
                ':total_freight' => $totalFreight,
                ':item_weight' => $itemWeight,
                ':total_weight' => $totalWeight,
                ':total_cnf_value' => $totalCnfValue,
                ':invoice_total' => $invoiceTotal,
                ':id' => $productId,
                ':invoice_id' => $invoiceId,
            ]);
        }
    } else {
        $invoiceTotalSum = $currentTotalValue;
    }

    $updateInvoice = $pdo->prepare(
        'UPDATE commercial_invoices
         SET invoice_number = :invoice_number,
             invoice_date = :invoice_date,
             total_value = :total_value,
             updated_at = NOW(),
             updated_by = :updated_by
         WHERE id = :id'
    );
    $updateInvoice->execute([
        ':invoice_number' => $invoiceNumber,
        ':invoice_date' => $invoiceDate,
        ':total_value' => number_format($invoiceTotalSum, 2, '.', ''),
        ':updated_by' => Auth::userId(),
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
        'message' => 'Commercial invoice updated.',
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
        'message' => 'Unable to update the commercial invoice right now.',
    ]);
}
