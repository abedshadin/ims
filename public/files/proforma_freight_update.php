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
$freightRaw = trim($_POST['freight_amount'] ?? '');

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

try {
    $pdo = Database::getConnection();

    $update = $pdo->prepare(
        'UPDATE proforma_invoices SET freight_amount = :freight_amount WHERE id = :id'
    );
    $update->execute([
        ':freight_amount' => $freight,
        ':id' => $piId,
    ]);

    if ($update->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'The selected proforma invoice could not be found.',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Freight updated.',
        'freight_amount' => number_format($freight, 2, '.', ''),
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to update freight at the moment.',
    ]);
}
