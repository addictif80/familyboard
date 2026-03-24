<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die(json_encode(['error' => 'Database connection failed']));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int)self::getInstance()->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Run any .sql migration files that have not yet been applied.
     * Tracks applied migrations in a `_migrations` table.
     */
    public static function autoMigrate(string $dir): void
    {
        $pdo = self::getInstance();

        $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
            filename   VARCHAR(255) PRIMARY KEY,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $applied = $pdo->query("SELECT filename FROM _migrations")
                       ->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_flip($applied);

        $files = glob($dir . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) continue;

            try {
                $pdo->exec((string)file_get_contents($file));
                $pdo->prepare("INSERT INTO _migrations (filename) VALUES (?)")
                    ->execute([$name]);
            } catch (\PDOException $e) {
                error_log("Migration $name failed: " . $e->getMessage());
            }
        }
    }
}
