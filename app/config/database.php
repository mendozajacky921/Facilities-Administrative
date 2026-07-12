<?php
/**
 * database.php
 * PDO connection factory. Reads credentials from .env (see config.php).
 *
 * NOTE: This database is SHARED across all 10 capstone teams.
 * Team 8 tables are prefixed `team8_`. Never DROP/ALTER shared core
 * tables (users, roles, departments, etc.) from this codebase.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'capstone_shared_db');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                // Local/dev: surface the real error to speed up debugging.
                die('Database connection failed: ' . $e->getMessage());
            }
            // Prod: never leak connection details.
            error_log('Database connection failed: ' . $e->getMessage());
            die('A database error occurred. Please try again later.');
        }

        return self::$connection;
    }

    // Prevent instantiation/cloning — this is a static connection factory.
    private function __construct() {}
    private function __clone() {}
}
