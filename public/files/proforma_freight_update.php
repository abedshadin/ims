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

$piToken = trim($_POST['pi_token'] ?? '');
$freightRaw = trim($_POST['freight_amount'] ?? '');
$toleranceRaw = trim($_POST['tolerance_percentage'] ?? '');

if ($piToken === '' || ($piId = IdCipher::decode($piToken)) === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid proforma invoice reference is required.',
    ]);
    exit;
}

if ($freightRaw === '') {
    $freightRaw = '0';
}

if (!is_numeric($freightRaw)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Freight must be provided as a numeric amount.',
    ]);
    exit;
}

$freight = (float) $freightRaw;

if ($freight < 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Freight cannot be negative.',
    ]);
    exit;
}

$tolerance = 0.0;

if ($toleranceRaw !== '') {
    if (!is_numeric($toleranceRaw)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Tolerance must be provided as a numeric percentage.',
        ]);
        exit;
    }

    $tolerance = (float) $toleranceRaw;
}

if ($tolerance < 0 || $tolerance > 100) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Tolerance must be between 0 and 100 percent.',
    ]);
    exit;
}

$tolerance = round($tolerance, 2);

try {
    $pdo = Database::getConnection();

    $invoiceStatement = $pdo->prepare(
        'SELECT vendor_file_id FROM proforma_invoices WHERE id = :id'
    );
    $invoiceStatement->execute([':id' => $piId]);
    $invoice = $invoiceStatement->fetch();

    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected proforma invoice could not be found.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $update = $pdo->prepare(
        'UPDATE proforma_invoices SET freight_amount = :freight_amount, tolerance_percentage = :tolerance_percentage WHERE id = :id'
    );
    $update->execute([
        ':freight_amount' => $freight,
        ':tolerance_percentage' => $tolerance,
        ':id' => $piId,
    ]);

    $updateFileStatement = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFileStatement->execute([
        ':updated_by' => Auth::userId(),
        ':id' => (int) $invoice['vendor_file_id'],
    ]);

    $pdo->commit();

    $fileMeta = FileMetadata::load($pdo, (int) $invoice['vendor_file_id']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Freight saved.',
        'freight_amount' => number_format($freight, 2, '.', ''),
        'freight_amount_formatted' => number_format($freight, 2),
        'tolerance_percentage' => number_format($tolerance, 2, '.', ''),
        'tolerance_percentage_formatted' => number_format($tolerance, 2),
        'file_meta' => $fileMeta,
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to update freight at the moment.',
    ]);
}
