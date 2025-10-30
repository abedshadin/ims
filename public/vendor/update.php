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
$vendorId = $vendorToken !== '' ? IdCipher::decode($vendorToken) : null;

if ($vendorId === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid vendor id is required.',
    ]);
    exit;
}

$requiredFields = [
    'vendor_name',
    'vendor_address',
    'beneficiary_bank_name',
    'beneficiary_bank_address',
    'beneficiary_bank_account',
    'beneficiary_swift',
    'advising_bank_name',
    'advising_bank_account',
    'advising_swift_code',
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

try {
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        'UPDATE vendors SET
            vendor_name = :vendor_name,
            vendor_address = :vendor_address,
            beneficiary_bank_name = :beneficiary_bank_name,
            beneficiary_bank_address = :beneficiary_bank_address,
            beneficiary_bank_account = :beneficiary_bank_account,
            beneficiary_swift = :beneficiary_swift,
            advising_bank_name = :advising_bank_name,
            advising_bank_account = :advising_bank_account,
            advising_swift_code = :advising_swift_code
        WHERE id = :id'
    );

    $statement->execute([
        ':vendor_name' => $input['vendor_name'],
        ':vendor_address' => $input['vendor_address'],
        ':beneficiary_bank_name' => $input['beneficiary_bank_name'],
        ':beneficiary_bank_address' => $input['beneficiary_bank_address'],
        ':beneficiary_bank_account' => $input['beneficiary_bank_account'],
        ':beneficiary_swift' => $input['beneficiary_swift'],
        ':advising_bank_name' => $input['advising_bank_name'],
        ':advising_bank_account' => $input['advising_bank_account'],
        ':advising_swift_code' => $input['advising_swift_code'],
        ':id' => $vendorId,
    ]);

    if ($statement->rowCount() === 0) {
        $checkStatement = $pdo->prepare('SELECT 1 FROM vendors WHERE id = :id');
        $checkStatement->execute([':id' => $vendorId]);

        if ($checkStatement->fetchColumn() === false) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Vendor not found.',
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'No changes were made. Vendor information is up to date.',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Vendor updated successfully.',
    ]);
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to update vendor information. Please try again later.',
    ]);
}
