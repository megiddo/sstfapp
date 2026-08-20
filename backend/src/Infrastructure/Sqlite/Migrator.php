<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    public function migrate(PDO $pdo, string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            throw new InvalidArgumentException('Migration directory does not exist: ' . $directory);
        }

        $this->ensureMigrationsTable($pdo);

        $applied = $this->appliedVersions($pdo);
        $pending = $this->pendingFiles($directory, $applied);

        foreach ($pending as $item) {
            $this->applyFile($pdo, $item['version'], $item['path']);
        }
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )',
        );
    }

    /**
     * @return list<string>
     */
    private function appliedVersions(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC');
        if ($stmt === false) {
            throw new RuntimeException('Unable to read schema_migrations');
        }

        $versions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($versions as $version) {
            if (is_string($version) && $version !== '') {
                $out[] = $version;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $applied
     * @return list<array{version: string, path: string}>
     */
    private function pendingFiles(string $directory, array $applied): array
    {
        $matches = glob($directory . '/*.sql');
        $files = $matches === false ? [] : $matches;
        $seen = [];
        $pending = [];

        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/^(\d+)_[A-Za-z0-9_-]+\.sql$/', $base, $match) !== 1) {
                continue;
            }

            $version = $match[1];
            if (in_array($version, $applied, true)) {
                continue;
            }
            if (isset($seen[$version])) {
                throw new RuntimeException('Duplicate migration version: ' . $version);
            }

            $seen[$version] = true;
            $pending[] = ['version' => $version, 'path' => $file];
        }

        usort(
            $pending,
            static fn (array $a, array $b): int => ((int) $a['version']) <=> ((int) $b['version']),
        );

        return $pending;
    }

    private function applyFile(PDO $pdo, string $version, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration file: ' . $path);
        }

        $pdo->beginTransaction();
        try {
            $trimmed = trim($sql);
            if ($trimmed !== '') {
                $pdo->exec($sql);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)',
            );
            $stmt->execute([
                'version' => $version,
                'applied_at' => gmdate('c'),
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
