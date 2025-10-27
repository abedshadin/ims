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
        'SELECT id FROM vendor_files WHERE id = :id'
    );
    $fileStatement->execute([':id' => $fileId]);

    if (!$fileStatement->fetch()) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The requested file could not be found.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $insertStatement = $pdo->prepare(
        'INSERT INTO proforma_invoices (vendor_file_id, invoice_number, freight_amount, created_at, created_by) '
        . 'VALUES (:vendor_file_id, :invoice_number, :freight_amount, NOW(), :created_by)'
    );
    $insertStatement->execute([
        ':vendor_file_id' => $fileId,
        ':invoice_number' => $invoiceNumber,
        ':freight_amount' => $freightAmount,
        ':created_by' => Auth::userId(),
    ]);

    $piId = (int) $pdo->lastInsertId();

    $updateFileStatement = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFileStatement->execute([
        ':updated_by' => Auth::userId(),
        ':id' => $fileId,
    ]);

    $pdo->commit();

    $createdAtStatement = $pdo->prepare(
        'SELECT created_at FROM proforma_invoices WHERE id = :id'
    );
    $createdAtStatement->execute([':id' => $piId]);
    $createdAtRow = $createdAtStatement->fetch();
    $createdAt = $createdAtRow ? (string) $createdAtRow['created_at'] : date('Y-m-d H:i:s');

    try {
        $piToken = IdCipher::encode($piId);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $piToken = null;
    }

    $fileMeta = FileMetadata::load($pdo, $fileId);

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
        'file_meta' => $fileMeta,
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to save the proforma invoice right now.',
    ]);
}
