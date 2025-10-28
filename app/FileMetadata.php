<?php

declare(strict_types=1);

require_once __DIR__ . '/IdCipher.php';

final class FileMetadata
{
    public static function load(PDO $pdo, int $fileId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT
                vf.id,
                vf.file_name,
                vf.vendor_id,
                vf.bank_name,
                vf.brand,
                vf.created_at,
                vf.updated_at,
                vf.created_by,
                vf.updated_by,
                v.vendor_name,
                creator.name AS created_by_name,
                updater.name AS updated_by_name
             FROM vendor_files vf
             INNER JOIN vendors v ON v.id = vf.vendor_id
             LEFT JOIN users creator ON creator.id = vf.created_by
             LEFT JOIN users updater ON updater.id = vf.updated_by
             WHERE vf.id = :id'
        );

        $statement->execute([':id' => $fileId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $createdAt = (string) $row['created_at'];
        $updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;

        $createdAtHuman = '';
        if ($createdAt !== '') {
            $createdAtHuman = date('j M Y, g:i A', strtotime($createdAt));
        }

        $updatedAtHuman = null;
        if ($updatedAt !== null && $updatedAt !== '') {
            $updatedAtHuman = date('j M Y, g:i A', strtotime($updatedAt));
        }

        $token = null;

        try {
            $token = IdCipher::encode($fileId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $token = null;
        }

        return [
            'id' => (int) $row['id'],
            'token' => $token,
            'file_name' => (string) $row['file_name'],
            'vendor_id' => (int) $row['vendor_id'],
            'vendor_name' => (string) $row['vendor_name'],
            'bank_name' => (string) $row['bank_name'],
            'brand' => (string) $row['brand'],
            'created_at' => $createdAt,
            'created_at_human' => $createdAtHuman,
            'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_by_name' => $row['created_by_name'] !== null ? (string) $row['created_by_name'] : null,
            'updated_at' => $updatedAt,
            'updated_at_human' => $updatedAtHuman,
            'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
            'updated_by_name' => $row['updated_by_name'] !== null ? (string) $row['updated_by_name'] : null,
        ];
    }
}
