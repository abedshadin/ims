<?php

declare(strict_types=1);

final class ProformaReference
{
    public static function ensure(PDO $pdo, int $proformaId, string $bankName): array
    {
        $existing = self::find($pdo, $proformaId);

        if ($existing !== null) {
            return $existing;
        }

        $bankCode = strtoupper(trim($bankName));

        if (!in_array($bankCode, ['DBBL', 'SCB', 'BBL'], true)) {
            $bankCode = 'BANK';
        }

        $sequence = self::nextSequence($pdo, $bankCode);
        $referenceCode = sprintf('TFL/SCM/%s/%d', $bankCode, $sequence);
        $referenceDate = date('Y-m-d');

        $insert = $pdo->prepare(
            'INSERT INTO proforma_invoice_references (proforma_invoice_id, bank_name, reference_code, reference_date, created_at)
             VALUES (:proforma_invoice_id, :bank_name, :reference_code, :reference_date, NOW())'
        );

        $insert->execute([
            ':proforma_invoice_id' => $proformaId,
            ':bank_name' => $bankCode,
            ':reference_code' => $referenceCode,
            ':reference_date' => $referenceDate,
        ]);

        return [
            'code' => $referenceCode,
            'date' => $referenceDate,
        ];
    }

    public static function updateDate(PDO $pdo, int $proformaId, string $date): array
    {
        $reference = self::find($pdo, $proformaId);

        if ($reference === null) {
            throw new RuntimeException('Reference not initialised.');
        }

        $normalised = self::normaliseDate($date);

        $update = $pdo->prepare(
            'UPDATE proforma_invoice_references SET reference_date = :reference_date WHERE proforma_invoice_id = :proforma_invoice_id'
        );

        $update->execute([
            ':reference_date' => $normalised,
            ':proforma_invoice_id' => $proformaId,
        ]);

        return [
            'code' => $reference['code'],
            'date' => $normalised,
        ];
    }

    public static function find(PDO $pdo, int $proformaId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT reference_code, reference_date FROM proforma_invoice_references WHERE proforma_invoice_id = :proforma_invoice_id LIMIT 1'
        );

        $statement->execute([':proforma_invoice_id' => $proformaId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'code' => (string) $row['reference_code'],
            'date' => (string) $row['reference_date'],
        ];
    }

    private static function nextSequence(PDO $pdo, string $bankCode): int
    {
        $statement = $pdo->prepare(
            'SELECT reference_code FROM proforma_invoice_references WHERE bank_name = :bank_name ORDER BY id DESC LIMIT 1'
        );

        $statement->execute([':bank_name' => $bankCode]);
        $row = $statement->fetch();

        if (!$row) {
            return 1;
        }

        $code = (string) $row['reference_code'];

        if (preg_match('/^(?:TFL\/SCM\/[^\/]+\/)(\d+)$/', $code, $matches) === 1) {
            $next = (int) $matches[1] + 1;
            return $next > 0 ? $next : 1;
        }

        return 1;
    }

    public static function normaliseDate(string $date): string
    {
        $trimmed = trim($date);

        if ($trimmed === '') {
            return date('Y-m-d');
        }

        $timestamp = strtotime($trimmed);

        if ($timestamp === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $timestamp);
    }
}
