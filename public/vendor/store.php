<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';

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

require_once __DIR__ . '/../../app/Database.php';

try {
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        'INSERT INTO vendors (
            vendor_name,
            vendor_address,
            beneficiary_bank_name,
            beneficiary_bank_address,
            beneficiary_bank_account,
            beneficiary_swift,
            advising_bank_name,
            advising_bank_account,
            advising_swift_code,
            created_at,
            created_by
        ) VALUES (
            :vendor_name,
            :vendor_address,
            :beneficiary_bank_name,
            :beneficiary_bank_address,
            :beneficiary_bank_account,
            :beneficiary_swift,
            :advising_bank_name,
            :advising_bank_account,
            :advising_swift_code,
            NOW(),
            :created_by
        )'
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
        ':created_by' => Auth::userId(),
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Vendor saved successfully.',
    ]);
} catch (\PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to save vendor information. Please try again later.',
    ]);
}
