<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/IdCipher.php';
require_once __DIR__ . '/../../app/FileMetadata.php';
require_once __DIR__ . '/../../app/FileInsuranceDetails.php';

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
    'money_receipt_no' => isset($_POST['money_receipt_no']) ? trim((string) $_POST['money_receipt_no']) : '',
    'money_receipt_date' => isset($_POST['money_receipt_date']) ? trim((string) $_POST['money_receipt_date']) : '',
    'exchange_rate' => isset($_POST['exchange_rate']) ? trim((string) $_POST['exchange_rate']) : '',
    'insurance_value' => isset($_POST['insurance_value']) ? trim((string) $_POST['insurance_value']) : '',
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

if ($input['money_receipt_no'] === '') {
    $errors['money_receipt_no'] = 'Money receipt number is required.';
}

$receiptDate = DateTime::createFromFormat('Y-m-d', $input['money_receipt_date']);
if ($input['money_receipt_date'] === '' || $receiptDate === false) {
    $errors['money_receipt_date'] = 'Please provide a valid money receipt date.';
}

if ($input['exchange_rate'] === '' || !is_numeric($input['exchange_rate'])) {
    $errors['exchange_rate'] = 'Exchange rate must be provided as a number.';
} elseif ((float) $input['exchange_rate'] < 0) {
    $errors['exchange_rate'] = 'Exchange rate cannot be negative.';
}

if ($input['insurance_value'] === '' || !is_numeric($input['insurance_value'])) {
    $errors['insurance_value'] = 'Insurance value must be provided as a number.';
} elseif ((float) $input['insurance_value'] < 0) {
    $errors['insurance_value'] = 'Insurance value cannot be negative.';
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

    $statement = $pdo->prepare(
        'SELECT id FROM file_insurance_details WHERE vendor_file_id = :file_id LIMIT 1'
    );
    $statement->execute([':file_id' => $fileId]);
    $existing = $statement->fetch();

    $receiptDateValue = $receiptDate !== false ? $receiptDate->format('Y-m-d') : null;
    $exchangeRateValue = number_format((float) $input['exchange_rate'], 4, '.', '');
    $insuranceValueValue = number_format((float) $input['insurance_value'], 2, '.', '');

    if ($existing) {
        $updateStatement = $pdo->prepare(
            'UPDATE file_insurance_details
             SET money_receipt_no = :money_receipt_no,
                 money_receipt_date = :money_receipt_date,
                 exchange_rate = :exchange_rate,
                 insurance_value = :insurance_value,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE vendor_file_id = :file_id'
        );

        $updateStatement->execute([
            ':money_receipt_no' => $input['money_receipt_no'],
            ':money_receipt_date' => $receiptDateValue,
            ':exchange_rate' => $exchangeRateValue,
            ':insurance_value' => $insuranceValueValue,
            ':updated_by' => $userId,
            ':file_id' => $fileId,
        ]);
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO file_insurance_details (
                vendor_file_id, money_receipt_no, money_receipt_date, exchange_rate, insurance_value, created_at, created_by
            ) VALUES (
                :file_id, :money_receipt_no, :money_receipt_date, :exchange_rate, :insurance_value, NOW(), :created_by
            )'
        );

        $insertStatement->execute([
            ':file_id' => $fileId,
            ':money_receipt_no' => $input['money_receipt_no'],
            ':money_receipt_date' => $receiptDateValue,
            ':exchange_rate' => $exchangeRateValue,
            ':insurance_value' => $insuranceValueValue,
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

    $insuranceDetails = FileInsuranceDetails::load($pdo, $fileId);
    $fileMeta = FileMetadata::load($pdo, $fileId);

    respond([
        'status' => 'success',
        'message' => 'Insurance details saved successfully.',
        'insurance' => $insuranceDetails,
        'file_meta' => $fileMeta,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'status' => 'error',
        'message' => 'Unable to save the insurance details right now.',
    ], 500);
}
