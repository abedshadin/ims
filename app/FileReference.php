<?php

declare(strict_types=1);

final class FileReference
{
    private const PREFIX = 'F';

    /**
     * Generate the next sequential file name using the pattern F/<year>/<sequence>.
     */
    public static function next(\PDO $connection, ?int $year = null, bool $lock = false): string
    {
        $year ??= (int) date('Y');
        $prefix = sprintf('%s/%d/', self::PREFIX, $year);

        $query = 'SELECT file_name FROM vendor_files WHERE file_name LIKE :prefix ORDER BY id DESC LIMIT 1';

        if ($lock) {
            $query .= ' FOR UPDATE';
        }

        $statement = $connection->prepare($query);
        $statement->execute([
            ':prefix' => $prefix . '%',
        ]);

        $latestFileName = $statement->fetchColumn();
        $sequence = 0;

        if (is_string($latestFileName)) {
            $parts = explode('/', $latestFileName);
            $sequencePart = array_pop($parts);

            if ($sequencePart !== null && ctype_digit($sequencePart)) {
                $sequence = (int) $sequencePart;
            }
        }

        return sprintf('%s%d', $prefix, $sequence + 1);
    }
}
