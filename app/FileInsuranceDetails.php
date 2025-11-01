<?php

declare(strict_types=1);

final class FileInsuranceDetails
{
    public static function load(PDO $pdo, int $fileId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id, vendor_file_id, money_receipt_no, money_receipt_date, exchange_rate, insurance_value, created_at, updated_at, created_by, updated_by
             FROM file_insurance_details
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
        $receiptDate = self::normaliseDate($row['money_receipt_date'] ?? null);
        $exchangeRate = self::normaliseDecimal($row['exchange_rate'] ?? null, 4);
        $insuranceValue = self::normaliseDecimal($row['insurance_value'] ?? null, 2);

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'vendor_file_id' => isset($row['vendor_file_id']) ? (int) $row['vendor_file_id'] : null,
            'money_receipt_no' => (string) ($row['money_receipt_no'] ?? ''),
            'money_receipt_date' => $receiptDate['value'],
            'money_receipt_date_human' => $receiptDate['human'],
            'exchange_rate' => $exchangeRate['value'],
            'exchange_rate_formatted' => $exchangeRate['formatted'],
            'insurance_value' => $insuranceValue['value'],
            'insurance_value_formatted' => $insuranceValue['formatted'],
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) && $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
        ];
    }

    private static function normaliseDecimal(mixed $value, int $decimals): array
    {
        if ($value === null || $value === '') {
            $formattedZero = number_format(0, $decimals);

            return [
                'value' => '',
                'formatted' => $formattedZero,
            ];
        }

        $number = (float) $value;

        return [
            'value' => number_format($number, $decimals, '.', ''),
            'formatted' => number_format($number, $decimals),
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
