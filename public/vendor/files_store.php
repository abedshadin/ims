<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileReference.php';

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

$bankOptions = ['DBBL', 'SCB', 'BBL'];
$brandOptions = ['PH', 'KFC', 'PH/KFC'];

$vendorToken = trim($_POST['vendor_id'] ?? '');
$bankName = trim($_POST['bank_name'] ?? '');
$brand = trim($_POST['brand'] ?? '');

if ($vendorToken === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Vendor selection is required.',
    ]);
    exit;
}

$vendorId = IdCipher::decode($vendorToken);

if ($vendorId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid vendor selection.',
    ]);
    exit;
}

if (!in_array($bankName, $bankOptions, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please choose a valid bank.',
    ]);
    exit;
}

if (!in_array($brand, $brandOptions, true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please choose a valid brand.',
    ]);
    exit;
}

$pdo = null;

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $vendorStatement = $pdo->prepare('SELECT COUNT(*) FROM vendors WHERE id = :id');
    $vendorStatement->execute([':id' => $vendorId]);

    if ((int) $vendorStatement->fetchColumn() === 0) {
        $pdo->rollBack();

        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Selected vendor was not found.',
        ]);
        exit;
    }

    $fileName = FileReference::next($pdo, null, true);

    $insertStatement = $pdo->prepare(
        'INSERT INTO vendor_files (
            file_name,
            vendor_id,
            bank_name,
            brand,
            created_at,
            created_by
        ) VALUES (
            :file_name,
            :vendor_id,
            :bank_name,
            :brand,
            NOW(),
            :created_by
        )'
    );

    $insertStatement->execute([
        ':file_name' => $fileName,
        ':vendor_id' => $vendorId,
        ':bank_name' => $bankName,
        ':brand' => $brand,
        ':created_by' => Auth::userId(),
    ]);

    $pdo->commit();

    $nextFileName = FileReference::next($pdo);

    echo json_encode([
        'status' => 'success',
        'message' => 'File created successfully.',
        'file_name' => $fileName,
        'next_file_name' => $nextFileName,
    ]);
} catch (PDOException $exception) {
    if ($pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create the file. Please try again later.',
    ]);
}
