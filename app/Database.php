<?php

declare(strict_types=1);

class Database
{
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'ims';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    private static ?\PDO $connection = null;

    /**
     * Get a singleton PDO connection instance.
     */
    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', self::DB_HOST, self::DB_NAME, self::DB_CHARSET);

            self::$connection = new \PDO(
                $dsn,
                self::DB_USER,
                self::DB_PASS,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        }

        return self::$connection;
    }
}
