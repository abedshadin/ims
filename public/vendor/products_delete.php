<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';

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

$vendorToken = isset($_POST['vendor_id']) ? (string) $_POST['vendor_id'] : '';
$productToken = isset($_POST['product_id']) ? (string) $_POST['product_id'] : '';

$vendorId = $vendorToken !== '' ? IdCipher::decode($vendorToken) : null;
$productId = $productToken !== '' ? IdCipher::decode($productToken) : null;

if ($vendorId === null || $productId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Valid vendor and product identifiers are required to delete a record.',
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();

    $checkStatement = $pdo->prepare(
        'SELECT 1 FROM vendor_products WHERE id = :id AND vendor_id = :vendor_id'
    );
    $checkStatement->execute([
        ':id' => $productId,
        ':vendor_id' => $vendorId,
    ]);

    if ($checkStatement->fetchColumn() === false) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The requested product could not be found for this vendor.',
        ]);
        exit;
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM vendor_products WHERE id = :id AND vendor_id = :vendor_id'
    );
    $deleteStatement->execute([
        ':id' => $productId,
        ':vendor_id' => $vendorId,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product deleted successfully.',
    ]);
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to delete the product right now. Please try again later.',
    ]);
}
