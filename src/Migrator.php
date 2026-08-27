<?php

namespace App;

use RuntimeException;

/**
 * Applies the numbered .sql files in migrations/<driver>/ in order, once each,
 * recording what it has done in schema_migrations.
 */
final class Migrator
{
    private Database $db;
    private string $driver;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->driver = $db->driver();
    }

    /**
     * @param callable|null $log receives one line per applied migration
     * @return array<int, string> versions applied by this call
     */
    public function run(?callable $log = null): array
    {
        $this->ensureLedger();

        $applied = $this->appliedVersions();
        $done = [];

        foreach ($this->available() as $version => $path) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            foreach (self::splitStatements((string) file_get_contents($path)) as $statement) {
                $this->db->pdo()->exec($statement);
            }

            $this->db->insert('schema_migrations', [
                'version'    => $version,
                'applied_at' => now(),
            ]);

            $done[] = $version;
            if ($log !== null) {
                $log($version);
            }
        }

        return $done;
    }

    /** @return array<string, string> version => file path, in order */
    public function available(): array
    {
        $dir = APP_ROOT . '/migrations/' . $this->driver;
        if (!is_dir($dir)) {
            throw new RuntimeException("No migrations directory for driver '{$this->driver}'.");
        }

        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $out = [];
        foreach ($files as $file) {
            $out[basename($file, '.sql')] = $file;
        }

        return $out;
    }

    /** @return array<int, string> */
    public function appliedVersions(): array
    {
        return array_map(
            static fn (array $row) => (string) $row['version'],
            $this->db->all('SELECT version FROM schema_migrations ORDER BY version')
        );
    }

    public function pending(): array
    {
        $this->ensureLedger();

        return array_values(array_diff(
            array_keys($this->available()),
            $this->appliedVersions()
        ));
    }

    private function ensureLedger(): void
    {
        if ($this->db->tableExists('schema_migrations')) {
            return;
        }

        $this->db->pdo()->exec(
            'CREATE TABLE schema_migrations (
                version    VARCHAR(64) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            )'
        );

        // A database created before the ledger existed already has the first
        // migration in it; record that rather than trying to apply it again.
        if ($this->db->tableExists('categories')) {
            $first = array_key_first($this->available());
            if ($first !== null) {
                $this->db->insert('schema_migrations', [
                    'version'    => $first,
                    'applied_at' => now(),
                ]);
            }
        }
    }

    /** @return array<int, string> */
    public static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $s) => $s !== ''
        ));
    }
}
