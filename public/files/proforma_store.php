<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';
require_once __DIR__ . '/../../app/ProformaReference.php';

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
$piHeader = trim($_POST['pi_header'] ?? '');
$freightAmountRaw = trim($_POST['freight_amount'] ?? '');
$lcToleranceEnabledRaw = $_POST['lc_tolerance_enabled'] ?? '';
$lcTolerancePercentageRaw = trim($_POST['lc_tolerance_percentage'] ?? '');

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

$piHeader = mb_substr($piHeader, 0, 255);

$freightAmount = 0.0;
$lcToleranceEnabled = false;
$lcTolerancePercentage = 10.0;

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

if ($lcToleranceEnabledRaw !== '') {
    $lcToleranceEnabled = filter_var($lcToleranceEnabledRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $lcToleranceEnabled = $lcToleranceEnabled === null ? false : $lcToleranceEnabled;
}

if ($lcToleranceEnabled) {
    if ($lcTolerancePercentageRaw === '') {
        $lcTolerancePercentage = 10.0;
    } elseif (!is_numeric($lcTolerancePercentageRaw)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Provide a numeric L/C tolerance percentage when enabled.',
        ]);
        exit;
    } else {
        $lcTolerancePercentage = (float) $lcTolerancePercentageRaw;

        if ($lcTolerancePercentage < 0 || $lcTolerancePercentage > 1000) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => 'The L/C tolerance percentage must be between 0 and 1000.',
            ]);
            exit;
        }
    }
} else {
    $lcTolerancePercentage = 0.0;
}

try {
    $pdo = Database::getConnection();

    $fileStatement = $pdo->prepare(
        'SELECT id, bank_name FROM vendor_files WHERE id = :id'
    );
    $fileStatement->execute([':id' => $fileId]);

    $fileRow = $fileStatement->fetch();

    if (!$fileRow) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The requested file could not be found.',
        ]);
        exit;
    }

    $bankName = (string) $fileRow['bank_name'];

    $pdo->beginTransaction();

    $insertStatement = $pdo->prepare(
        'INSERT INTO proforma_invoices (vendor_file_id, invoice_number, pi_header, lc_tolerance_enabled, lc_tolerance_percentage, freight_amount, created_at, created_by) '
        . 'VALUES (:vendor_file_id, :invoice_number, :pi_header, :lc_tolerance_enabled, :lc_tolerance_percentage, :freight_amount, NOW(), :created_by)'
    );
    $insertStatement->execute([
        ':vendor_file_id' => $fileId,
        ':invoice_number' => $invoiceNumber,
        ':pi_header' => $piHeader,
        ':lc_tolerance_enabled' => $lcToleranceEnabled ? 1 : 0,
        ':lc_tolerance_percentage' => $lcToleranceEnabled ? $lcTolerancePercentage : 0,
        ':freight_amount' => $freightAmount,
        ':created_by' => Auth::userId(),
    ]);

    $piId = (int) $pdo->lastInsertId();

    $reference = ProformaReference::ensure($pdo, $piId, $bankName);

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
            'pi_header' => $piHeader,
            'lc_tolerance_enabled' => $lcToleranceEnabled,
            'lc_tolerance_percentage' => $lcToleranceEnabled ? number_format($lcTolerancePercentage, 2, '.', '') : '0.00',
            'lc_tolerance_percentage_formatted' => $lcToleranceEnabled ? number_format($lcTolerancePercentage, 2) : '0.00',
            'freight_amount' => number_format($freightAmount, 2, '.', ''),
            'created_at' => $createdAt,
            'created_at_human' => date('j M Y, g:i A', strtotime($createdAt)),
            'products' => [],
            'reference' => [
                'code' => $reference['code'] ?? null,
                'date' => $reference['date'] ?? null,
                'date_formatted' => isset($reference['date']) ? date('j M Y', strtotime($reference['date'])) : null,
            ],
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
