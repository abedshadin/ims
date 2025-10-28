<?php

declare(strict_types=1);

final class BankDirectory
{
    public static function findByCode(PDO $pdo, string $code): ?array
    {
        $normalised = strtoupper(trim($code));

        if ($normalised === '') {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT bank_code, bank_name, address_line1, address_line2, address_line3, account_name, account_number
             FROM bank_directory
             WHERE UPPER(bank_code) = :code
             LIMIT 1'
        );

        $statement->execute([':code' => $normalised]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $addressLines = [
            isset($row['address_line1']) ? (string) $row['address_line1'] : '',
            isset($row['address_line2']) ? (string) $row['address_line2'] : '',
            isset($row['address_line3']) ? (string) $row['address_line3'] : '',
        ];

        $addressLines = array_values(array_filter($addressLines, static fn(string $line): bool => trim($line) !== ''));

        return [
            'code' => (string) $row['bank_code'],
            'name' => (string) $row['bank_name'],
            'address_lines' => $addressLines,
            'account_name' => isset($row['account_name']) ? (string) $row['account_name'] : '',
            'account_number' => isset($row['account_number']) ? (string) $row['account_number'] : '',
        ];
    }
}
