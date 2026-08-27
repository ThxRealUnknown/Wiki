<?php

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper around a single SQLite database — one file inside the
 * project, no server to start or stop.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;
    private string $driver;

    private function __construct(PDO $pdo, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }

        return self::$instance;
    }

    public static function connect(?string $driver = null): Database
    {
        $driver = $driver ?? Config::get('driver', 'sqlite');
        $conf = Config::get($driver);
        if (!is_array($conf)) {
            throw new RuntimeException("No configuration block for driver '{$driver}'.");
        }

        $dsn = self::buildDsn($driver, $conf);

        try {
            $pdo = new PDO($dsn, $conf['username'] ?? null, $conf['password'] ?? null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Foreign keys are off by default in SQLite (would silently break
            // ON DELETE CASCADE). WAL survives an interrupted process better
            // than the default journal — useful for a folder that might live
            // on a USB stick.
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Could not connect to the {$driver} database: " . $e->getMessage(),
                0,
                $e
            );
        }

        return new self($pdo, $driver);
    }

    private static function buildDsn(string $driver, array $conf): string
    {
        if ($driver !== 'sqlite') {
            throw new RuntimeException("Unsupported driver '{$driver}'. Use 'sqlite'.");
        }

        // A relative path is resolved against the project root, not the cwd.
        $path = (string) ($conf['path'] ?? 'data/worldbuilder.sqlite');
        if (!preg_match('~^([A-Za-z]:[\\\\/]|/)~', $path)) {
            $path = APP_ROOT . '/' . $path;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return 'sqlite:' . $path;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @return int the primary key of the inserted row
     */
    public function insert(string $table, array $data): int
    {
        // Assigns a guid here so every insert site gets one automatically.
        if (in_array($table, Guid::TABLES, true) && !isset($data['guid'])) {
            $data['guid'] = Guid::make();
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);

        $sql = 'INSERT INTO ' . $table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $this->run($sql, $data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }

        $data['pk_id'] = $id;
        $this->run(
            'UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE id = :pk_id',
            $data
        );
    }

    public function delete(string $table, int $id): void
    {
        $this->run('DELETE FROM ' . $table . ' WHERE id = :id', ['id' => $id]);
    }

    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
