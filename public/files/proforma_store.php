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

$fileToken = trim($_POST['file_token'] ?? '');
$invoiceNumber = trim($_POST['invoice_number'] ?? '');
$freightAmountRaw = trim($_POST['freight_amount'] ?? '');

if ($fileToken === '' || ($fileId = IdCipher::decode($fileToken)) === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid file reference is required.',
    ]);
    exit;
}

if ($invoiceNumber === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'The proforma invoice number is required.',
    ]);
    exit;
}

$freightAmount = 0.0;

if ($freightAmountRaw !== '') {
    if (!is_numeric($freightAmountRaw)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Freight must be provided as a numeric amount.',
        ]);
        exit;
    }

    $freightAmount = (float) $freightAmountRaw;

    if ($freightAmount < 0) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Freight cannot be negative.',
        ]);
        exit;
    }
}

try {
    $pdo = Database::getConnection();

    $fileStatement = $pdo->prepare(
        'SELECT id, vendor_id FROM vendor_files WHERE id = :id'
    );
    $fileStatement->execute([':id' => $fileId]);
    $file = $fileStatement->fetch();

    if (!$file) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The requested file could not be found.',
        ]);
        exit;
    }

    $insertStatement = $pdo->prepare(
        'INSERT INTO proforma_invoices (vendor_file_id, invoice_number, freight_amount, created_at, created_by)
         VALUES (:vendor_file_id, :invoice_number, :freight_amount, NOW(), :created_by)'
    );
    $insertStatement->execute([
        ':vendor_file_id' => $fileId,
        ':invoice_number' => $invoiceNumber,
        ':freight_amount' => $freightAmount,
        ':created_by' => Auth::userId(),
    ]);

    $piId = (int) $pdo->lastInsertId();

    try {
        $piToken = IdCipher::encode($piId);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $piToken = null;
    }

    $createdAt = date('Y-m-d H:i:s');

    echo json_encode([
        'status' => 'success',
        'message' => 'Proforma invoice added.',
        'proforma' => [
            'token' => $piToken,
            'invoice_number' => $invoiceNumber,
            'freight_amount' => number_format($freightAmount, 2, '.', ''),
            'created_at' => $createdAt,
            'created_at_human' => date('j M Y, g:i A', strtotime($createdAt)),
            'products' => [],
        ],
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to save the proforma invoice right now.',
    ]);
}
