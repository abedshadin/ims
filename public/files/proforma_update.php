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

$piToken = trim($_POST['pi_token'] ?? '');
$piHeader = trim($_POST['pi_header'] ?? '');
$referenceDateInput = trim($_POST['reference_date'] ?? '');
$telerangePercentageRaw = trim($_POST['telerange_percentage'] ?? '');

if ($piToken === '' || ($piId = IdCipher::decode($piToken)) === null) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid proforma invoice reference is required.',
    ]);
    exit;
}

$piHeader = mb_substr($piHeader, 0, 255);
$telerangePercentage = 0.0;

if ($telerangePercentageRaw !== '') {
    if (!is_numeric($telerangePercentageRaw)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Provide the telerange percentage as a numeric value.',
        ]);
        exit;
    }

    $telerangePercentage = (float) $telerangePercentageRaw;

    if ($telerangePercentage < 0 || $telerangePercentage > 1000) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'The telerange percentage must be between 0 and 1000.',
        ]);
        exit;
    }
}

try {
    $pdo = Database::getConnection();

    $invoiceStatement = $pdo->prepare(
        'SELECT pi.id, pi.invoice_number, pi.vendor_file_id, vf.bank_name
         FROM proforma_invoices pi
         INNER JOIN vendor_files vf ON vf.id = pi.vendor_file_id
         WHERE pi.id = :id
         LIMIT 1'
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

    $updateProforma = $pdo->prepare(
        'UPDATE proforma_invoices SET pi_header = :pi_header, lc_tolerance_enabled = :lc_tolerance_enabled, lc_tolerance_percentage = :lc_tolerance_percentage WHERE id = :id'
    );
    $updateProforma->execute([
        ':pi_header' => $piHeader,
        ':lc_tolerance_enabled' => 0,
        ':lc_tolerance_percentage' => $telerangePercentage,
        ':id' => $piId,
    ]);

    $reference = ProformaReference::ensure($pdo, $piId, (string) $invoice['bank_name']);
    $normalisedDate = ProformaReference::normaliseDate($referenceDateInput);
    $reference = ProformaReference::updateDate($pdo, $piId, $normalisedDate);

    $updateFileStatement = $pdo->prepare(
        'UPDATE vendor_files SET updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
    );
    $updateFileStatement->execute([
        ':updated_by' => Auth::userId(),
        ':id' => (int) $invoice['vendor_file_id'],
    ]);

    $pdo->commit();

    try {
        $piTokenEncoded = IdCipher::encode($piId);
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $piTokenEncoded = null;
    }

    $fileMeta = FileMetadata::load($pdo, (int) $invoice['vendor_file_id']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Proforma details saved.',
        'proforma' => [
            'token' => $piTokenEncoded,
            'invoice_number' => (string) $invoice['invoice_number'],
            'pi_header' => $piHeader,
            'telerange_percentage' => number_format($telerangePercentage, 2, '.', ''),
            'telerange_percentage_formatted' => number_format($telerangePercentage, 2),
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
        'message' => 'Unable to update the proforma invoice details at the moment.',
    ]);
}
