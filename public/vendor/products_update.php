<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';

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

$vendorId = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($vendorId <= 0 || $productId <= 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Valid vendor and product ids are required.',
    ]);
    exit;
}

$requiredFields = [
    'product_name',
    'product_size',
    'unit',
    'rate',
    'item_weight',
    'dec_unit_price',
    'asses_unit_price',
    'hs_code',
];

$input = [];
foreach ($requiredFields as $field) {
    $value = trim($_POST[$field] ?? '');
    if ($value === '') {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => sprintf('Field "%s" is required.', str_replace('_', ' ', $field)),
        ]);
        exit;
    }

    $input[$field] = $value;
}

$numericFields = ['rate', 'dec_unit_price', 'asses_unit_price'];

foreach ($numericFields as $field) {
    if (!is_numeric($input[$field])) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => sprintf('Field "%s" must be a numeric value.', str_replace('_', ' ', $field)),
        ]);
        exit;
    }

    $input[$field] = number_format((float) $input[$field], 2, '.', '');
}

try {
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        'UPDATE vendor_products SET
            product_name = :product_name,
            product_size = :product_size,
            unit = :unit,
            rate = :rate,
            item_weight = :item_weight,
            dec_unit_price = :dec_unit_price,
            asses_unit_price = :asses_unit_price,
            hs_code = :hs_code,
            updated_at = NOW()
        WHERE id = :id AND vendor_id = :vendor_id'
    );

    $statement->execute([
        ':product_name' => $input['product_name'],
        ':product_size' => $input['product_size'],
        ':unit' => $input['unit'],
        ':rate' => $input['rate'],
        ':item_weight' => $input['item_weight'],
        ':dec_unit_price' => $input['dec_unit_price'],
        ':asses_unit_price' => $input['asses_unit_price'],
        ':hs_code' => $input['hs_code'],
        ':id' => $productId,
        ':vendor_id' => $vendorId,
    ]);

    if ($statement->rowCount() === 0) {
        $checkStatement = $pdo->prepare('SELECT 1 FROM vendor_products WHERE id = :id AND vendor_id = :vendor_id');
        $checkStatement->execute([
            ':id' => $productId,
            ':vendor_id' => $vendorId,
        ]);

        if ($checkStatement->fetchColumn() === false) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Product not found.',
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'No changes were made. Product information is up to date.',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Product updated successfully.',
    ]);
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to update product information. Please try again later.',
    ]);
}
