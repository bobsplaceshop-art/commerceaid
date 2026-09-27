<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin wrapper over PDO. Every query goes through prepared statements.
 */
final class Database
{
    /** @var array<string,mixed> */
    private array $config;
    private ?PDO $pdo = null;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = $this->config['driver'] ?? 'mysql';
        if ($driver !== 'mysql') {
            throw new RuntimeException("Unsupported DB driver: {$driver}");
        }

        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $name = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            $this->pdo = new PDO(
                $dsn,
                (string) ($this->config['username'] ?? ''),
                (string) ($this->config['password'] ?? ''),
                (array) ($this->config['options'] ?? [])
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed. Check config/local.php.', 0, $e);
        }

        return $this->pdo;
    }

    /** @param array<int,mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int,mixed> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<int,mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<int,mixed> $params */
    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void { $this->pdo()->beginTransaction(); }
    public function commit(): void           { $this->pdo()->commit(); }
    public function rollBack(): void         { if ($this->pdo()->inTransaction()) $this->pdo()->rollBack(); }

    public function testConnection(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
