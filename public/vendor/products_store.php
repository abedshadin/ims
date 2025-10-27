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

if ($vendorId <= 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid vendor id is required.',
    ]);
    exit;
}

$requiredFields = [
    'product_name',
    'brand',
    'country_of_origin',
    'product_category',
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

$allowedBrandValues = ['KFC', 'PH', 'KFC/PH'];
if (!in_array($input['brand'], $allowedBrandValues, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Brand selection is invalid.',
    ]);
    exit;
}

$allowedCategories = ['RM', 'EQ'];
if (!in_array($input['product_category'], $allowedCategories, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Product category selection is invalid.',
    ]);
    exit;
}

$allowedSizes = ['Carton', 'Case', 'MTN'];
if (!in_array($input['product_size'], $allowedSizes, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Size selection is invalid.',
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();

    $vendorCheck = $pdo->prepare('SELECT 1 FROM vendors WHERE id = :id');
    $vendorCheck->execute([':id' => $vendorId]);

    if ($vendorCheck->fetchColumn() === false) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Vendor not found.',
        ]);
        exit;
    }

    $statement = $pdo->prepare(
        'INSERT INTO vendor_products (
            vendor_id,
            product_name,
            brand,
            country_of_origin,
            product_category,
            product_size,
            unit,
            rate,
            item_weight,
            dec_unit_price,
            asses_unit_price,
            hs_code,
            created_at,
            updated_at
        ) VALUES (
            :vendor_id,
            :product_name,
            :brand,
            :country_of_origin,
            :product_category,
            :product_size,
            :unit,
            :rate,
            :item_weight,
            :dec_unit_price,
            :asses_unit_price,
            :hs_code,
            NOW(),
            NOW()
        )'
    );

    $statement->execute([
        ':vendor_id' => $vendorId,
        ':product_name' => $input['product_name'],
        ':brand' => $input['brand'],
        ':country_of_origin' => $input['country_of_origin'],
        ':product_category' => $input['product_category'],
        ':product_size' => $input['product_size'],
        ':unit' => $input['unit'],
        ':rate' => $input['rate'],
        ':item_weight' => $input['item_weight'],
        ':dec_unit_price' => $input['dec_unit_price'],
        ':asses_unit_price' => $input['asses_unit_price'],
        ':hs_code' => $input['hs_code'],
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product added successfully.',
    ]);
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to save product information. Please try again later.',
    ]);
}
