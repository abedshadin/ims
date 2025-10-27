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

$piToken = trim($_POST['pi_token'] ?? '');
$productToken = trim($_POST['product_token'] ?? '');

$piId = $piToken !== '' ? IdCipher::decode($piToken) : null;
$productId = $productToken !== '' ? IdCipher::decode($productToken) : null;

if ($piId === null || $productId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid proforma and product reference are required.',
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();

    $productStatement = $pdo->prepare(
        'SELECT pip.id, pip.proforma_invoice_id
         FROM proforma_invoice_products pip
         INNER JOIN proforma_invoices pi ON pi.id = pip.proforma_invoice_id
         WHERE pip.id = :product_id AND pip.proforma_invoice_id = :pi_id'
    );
    $productStatement->execute([
        ':product_id' => $productId,
        ':pi_id' => $piId,
    ]);
    $product = $productStatement->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected product could not be found for this proforma invoice.',
        ]);
        exit;
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM proforma_invoice_products WHERE id = :product_id AND proforma_invoice_id = :pi_id'
    );
    $deleteStatement->execute([
        ':product_id' => $productId,
        ':pi_id' => $piId,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product removed from the proforma invoice.',
        'product_token' => $productToken,
        'pi_token' => $piToken,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to remove the product right now.',
    ]);
}
