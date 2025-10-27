<?php

declare(strict_types=1);

final class FileLcDetails
{
    public static function load(PDO $pdo, int $fileId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id, vendor_file_id, lc_number, lc_date, lc_type, lc_amount, latest_shipment_date, expiry_date, created_at, updated_at, created_by, updated_by
             FROM file_letters_of_credit
             WHERE vendor_file_id = :file_id
             LIMIT 1'
        );

        $statement->execute([':file_id' => $fileId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return self::normaliseRow($row);
    }

    public static function normaliseRow(array $row): array
    {
        $lcAmount = self::normaliseNumber($row['lc_amount'] ?? null);

        $lcDate = self::normaliseDate($row['lc_date'] ?? null);
        $latestShipment = self::normaliseDate($row['latest_shipment_date'] ?? null);
        $expiryDate = self::normaliseDate($row['expiry_date'] ?? null);

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'vendor_file_id' => isset($row['vendor_file_id']) ? (int) $row['vendor_file_id'] : null,
            'lc_number' => (string) ($row['lc_number'] ?? ''),
            'lc_type' => (string) ($row['lc_type'] ?? ''),
            'lc_amount' => $lcAmount['value'],
            'lc_amount_formatted' => $lcAmount['formatted'],
            'lc_date' => $lcDate['value'],
            'lc_date_human' => $lcDate['human'],
            'latest_shipment_date' => $latestShipment['value'],
            'latest_shipment_date_human' => $latestShipment['human'],
            'expiry_date' => $expiryDate['value'],
            'expiry_date_human' => $expiryDate['human'],
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) && $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
        ];
    }

    private static function normaliseNumber(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [
                'value' => '',
                'formatted' => '0.00',
            ];
        }

        $number = (float) $value;

        return [
            'value' => number_format($number, 2, '.', ''),
            'formatted' => number_format($number, 2),
        ];
    }

    private static function normaliseDate(mixed $value): array
    {
        if (empty($value)) {
            return [
                'value' => '',
                'human' => '',
            ];
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return [
                'value' => '',
                'human' => '',
            ];
        }

        return [
            'value' => date('Y-m-d', $timestamp),
            'human' => date('j M Y', $timestamp),
        ];
    }
}
