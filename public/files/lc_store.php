<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';
require_once __DIR__ . '/../../app/FileLcDetails.php';

Auth::requireLogin('/auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST requests are allowed.',
    ]);
    return;
}

header('Content-Type: application/json');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$input = [
    'file_token' => isset($_POST['file_token']) ? trim((string) $_POST['file_token']) : '',
    'lc_number' => isset($_POST['lc_number']) ? trim((string) $_POST['lc_number']) : '',
    'lc_date' => isset($_POST['lc_date']) ? trim((string) $_POST['lc_date']) : '',
    'lc_type' => isset($_POST['lc_type']) ? trim((string) $_POST['lc_type']) : '',
    'currency' => isset($_POST['currency']) ? strtoupper(trim((string) $_POST['currency'])) : '',
    'subject_line' => isset($_POST['subject_line']) ? trim((string) $_POST['subject_line']) : '',
    'lc_amount' => isset($_POST['lc_amount']) ? trim((string) $_POST['lc_amount']) : '',
    'latest_shipment_date' => isset($_POST['latest_shipment_date']) ? trim((string) $_POST['latest_shipment_date']) : '',
    'expiry_date' => isset($_POST['expiry_date']) ? trim((string) $_POST['expiry_date']) : '',
];

if ($input['file_token'] === '') {
    respond([
        'status' => 'error',
        'message' => 'The file reference is required.',
    ], 422);
}

$fileId = IdCipher::decode($input['file_token']);

if ($fileId === null) {
    respond([
        'status' => 'error',
        'message' => 'A valid file reference could not be determined.',
    ], 422);
}

$errors = [];

if ($input['lc_number'] === '') {
    $errors['lc_number'] = 'LC number is required.';
}

if ($input['lc_type'] === '') {
    $errors['lc_type'] = 'LC type is required.';
}

if ($input['currency'] === '' || !in_array($input['currency'], ['USD', 'EURO'], true)) {
    $errors['currency'] = 'Please select a valid currency.';
}

if ($input['subject_line'] !== '' && strlen($input['subject_line']) > 255) {
    $errors['subject_line'] = 'Subject line must be 255 characters or fewer.';
}

if ($input['lc_amount'] === '' || !is_numeric($input['lc_amount'])) {
    $errors['lc_amount'] = 'LC amount must be provided as a number.';
}

$parsedLcDate = DateTime::createFromFormat('Y-m-d', $input['lc_date']);
if ($input['lc_date'] === '' || $parsedLcDate === false) {
    $errors['lc_date'] = 'Please provide a valid LC date.';
}

$parsedShipmentDate = DateTime::createFromFormat('Y-m-d', $input['latest_shipment_date']);
if ($input['latest_shipment_date'] === '' || $parsedShipmentDate === false) {
    $errors['latest_shipment_date'] = 'Please provide the latest date of shipment.';
}

$parsedExpiryDate = DateTime::createFromFormat('Y-m-d', $input['expiry_date']);
if ($input['expiry_date'] === '' || $parsedExpiryDate === false) {
    $errors['expiry_date'] = 'Please provide a valid expiry date.';
}

if (!empty($errors)) {
    respond([
        'status' => 'error',
        'message' => 'Please fix the highlighted errors.',
        'errors' => $errors,
    ], 422);
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $file = FileMetadata::load($pdo, $fileId);

    if ($file === null) {
        $pdo->rollBack();
        respond([
            'status' => 'error',
            'message' => 'The selected file could not be found.',
        ], 404);
    }

    $userId = Auth::userId();

    $lcStatement = $pdo->prepare(
        'SELECT id FROM file_letters_of_credit WHERE vendor_file_id = :file_id LIMIT 1'
    );
    $lcStatement->execute([':file_id' => $fileId]);
    $existing = $lcStatement->fetch();

    if ($existing) {
        $updateStatement = $pdo->prepare(
            'UPDATE file_letters_of_credit
             SET lc_number = :lc_number,
                 lc_date = :lc_date,
                 lc_type = :lc_type,
                 currency = :currency,
                 subject_line = :subject_line,
                 lc_amount = :lc_amount,
                 latest_shipment_date = :latest_shipment_date,
                 expiry_date = :expiry_date,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE vendor_file_id = :file_id'
        );

        $updateStatement->execute([
            ':lc_number' => $input['lc_number'],
            ':lc_date' => $parsedLcDate->format('Y-m-d'),
            ':lc_type' => $input['lc_type'],
            ':currency' => $input['currency'],
            ':subject_line' => $input['subject_line'],
            ':lc_amount' => number_format((float) $input['lc_amount'], 2, '.', ''),
            ':latest_shipment_date' => $parsedShipmentDate->format('Y-m-d'),
            ':expiry_date' => $parsedExpiryDate->format('Y-m-d'),
            ':updated_by' => $userId,
            ':file_id' => $fileId,
        ]);
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO file_letters_of_credit (
                vendor_file_id, lc_number, lc_date, lc_type, currency, subject_line, lc_amount, latest_shipment_date, expiry_date, created_at, created_by
            ) VALUES (
                :file_id, :lc_number, :lc_date, :lc_type, :currency, :subject_line, :lc_amount, :latest_shipment_date, :expiry_date, NOW(), :created_by
            )'
        );

        $insertStatement->execute([
            ':file_id' => $fileId,
            ':lc_number' => $input['lc_number'],
            ':lc_date' => $parsedLcDate->format('Y-m-d'),
            ':lc_type' => $input['lc_type'],
            ':currency' => $input['currency'],
            ':subject_line' => $input['subject_line'],
            ':lc_amount' => number_format((float) $input['lc_amount'], 2, '.', ''),
            ':latest_shipment_date' => $parsedShipmentDate->format('Y-m-d'),
            ':expiry_date' => $parsedExpiryDate->format('Y-m-d'),
            ':created_by' => $userId,
        ]);
    }

    if ($userId !== null) {
        $updateFileStatement = $pdo->prepare(
            'UPDATE vendor_files SET updated_at = NOW(), updated_by = :user_id WHERE id = :file_id'
        );
        $updateFileStatement->execute([
            ':user_id' => $userId,
            ':file_id' => $fileId,
        ]);
    }

    $pdo->commit();

    $lcDetails = FileLcDetails::load($pdo, $fileId);
    $fileMeta = FileMetadata::load($pdo, $fileId);

    respond([
        'status' => 'success',
        'message' => 'Letter of credit details saved successfully.',
        'lc' => $lcDetails,
        'file_meta' => $fileMeta,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'status' => 'error',
        'message' => 'Unable to save the letter of credit details right now.',
    ], 500);
}
