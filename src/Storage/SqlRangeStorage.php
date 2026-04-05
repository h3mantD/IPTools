<?php

declare(strict_types=1);

namespace IPTools\Storage;

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PDO-based range storage (MySQL, PostgreSQL, SQLite).
 *
 * Stores ranges as 16-byte binary (via AddressCodec) with a version column
 * for efficient indexed lookups. Containment queries use simple binary
 * comparisons: `start_bin <= addr AND end_bin >= addr`.
 *
 * Table existence is lazily verified on first use and cached for the
 * lifetime of the instance.
 */
final class SqlRangeStorage implements RangeStorageInterface
{
    private readonly string $table;

    private bool $tableVerified = false;

    public function __construct(private readonly PDO $pdo, string $table = 'ip_ranges')
    {
        // Whitelist: identifier-safe characters only (alphanumeric + underscore, starts with letter or underscore)
        if (preg_match('/^[A-Za-z_]\w*$/', $table) !== 1) {
            throw new RuntimeException('Invalid table name');
        }

        $this->table = $table;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(Network|Range $range, array $metadata = []): void
    {
        $this->ensureTableExists();
        [$version, $startBin, $endBin] = $this->normalizeRange($range);

        $statement = $this->pdo->prepare(
            sprintf(
                'INSERT INTO %s (version, start_bin, end_bin, metadata) VALUES (:version, :start_bin, :end_bin, :metadata)',
                $this->table
            )
        );

        $statement->bindValue(':version', $version, PDO::PARAM_INT);
        $statement->bindValue(':start_bin', $startBin, PDO::PARAM_LOB);
        $statement->bindValue(':end_bin', $endBin, PDO::PARAM_LOB);
        $statement->bindValue(':metadata', json_encode($metadata, JSON_THROW_ON_ERROR));
        $statement->execute();
    }

    public function delete(Network|Range $range): void
    {
        $this->ensureTableExists();
        [$version, $startBin, $endBin] = $this->normalizeRange($range);

        $statement = $this->pdo->prepare(
            sprintf(
                'DELETE FROM %s WHERE version = :version AND start_bin = :start_bin AND end_bin = :end_bin',
                $this->table
            )
        );

        $statement->bindValue(':version', $version, PDO::PARAM_INT);
        $statement->bindValue(':start_bin', $startBin, PDO::PARAM_LOB);
        $statement->bindValue(':end_bin', $endBin, PDO::PARAM_LOB);
        $statement->execute();
    }

    public function contains(IP $ip): bool
    {
        $this->ensureTableExists();
        $version = $this->versionToInt($ip);
        $encoded = AddressCodec::to16($ip);

        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT 1 FROM %s WHERE version = :version AND start_bin <= :addr_bin_start AND end_bin >= :addr_bin_end LIMIT 1',
                $this->table
            )
        );

        $statement->bindValue(':version', $version, PDO::PARAM_INT);
        $statement->bindValue(':addr_bin_start', $encoded, PDO::PARAM_LOB);
        $statement->bindValue(':addr_bin_end', $encoded, PDO::PARAM_LOB);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return iterable<array{range: Range, metadata: array<string, mixed>}>
     */
    public function findContaining(IP $ip): iterable
    {
        $this->ensureTableExists();
        $version = $this->versionToInt($ip);
        $encoded = AddressCodec::to16($ip);

        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT start_bin, end_bin, metadata FROM %s WHERE version = :version AND start_bin <= :addr_bin AND end_bin >= :addr_bin ORDER BY start_bin, end_bin',
                $this->table
            )
        );

        $statement->bindValue(':version', $version, PDO::PARAM_INT);
        $statement->bindValue(':addr_bin', $encoded, PDO::PARAM_LOB);
        $statement->execute();

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @var array{start_bin: mixed, end_bin: mixed, metadata: mixed} $row */
            $start = AddressCodec::from16($this->readBinaryColumn($row['start_bin']), $version);
            $end = AddressCodec::from16($this->readBinaryColumn($row['end_bin']), $version);

            /** @var array<string, mixed> $metadata */
            $metadata = [];
            $metadataJson = $this->readTextColumn($row['metadata']);
            if ($metadataJson !== null && $metadataJson !== '') {
                $decoded = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $metadata = $decoded;
                }
            }

            yield [
                'range' => new Range($start, $end),
                'metadata' => $metadata,
            ];
        }
    }

    public function count(): int
    {
        $this->ensureTableExists();
        $statement = $this->pdo->query(sprintf('SELECT COUNT(*) FROM %s', $this->table));
        if ($statement === false) {
            throw new RuntimeException(sprintf('Failed to execute count query on table "%s"', $this->table));
        }

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function normalizeRange(Network|Range $range): array
    {
        $first = $range->getFirstIP();
        $last = $range->getLastIP();

        return [
            $this->versionToInt($first),
            AddressCodec::to16($first),
            AddressCodec::to16($last),
        ];
    }

    private function versionToInt(IP $ip): int
    {
        return $ip->getVersion()->toInt();
    }

    /**
     * Lazy table existence check — runs a trivial SELECT on first use,
     * then caches the result. Provides a clear error message directing
     * users to run migrations if the table is missing.
     */
    private function ensureTableExists(): void
    {
        if ($this->tableVerified) {
            return;
        }

        try {
            $statement = $this->pdo->query(sprintf('SELECT 1 FROM %s LIMIT 1', $this->table));
            if ($statement !== false) {
                $statement->closeCursor();
            }
            $this->tableVerified = true;
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                sprintf(
                    'Table "%s" is not available. Create it before using DB-backed range storage. If you use Laravel, run "php artisan vendor:publish --tag=iptools-migrations" then "php artisan migrate".',
                    $this->table
                ),
                0,
                $throwable
            );
        }
    }

    /**
     * Read a BLOB column that PDO may return as a stream resource (PostgreSQL)
     * or as a raw string (MySQL, SQLite).
     */
    private function readBinaryColumn(mixed $value): string
    {
        if (is_resource($value)) {
            $content = stream_get_contents($value);

            if ($content === false) {
                throw new RuntimeException('Unable to read binary column stream');
            }

            return $content;
        }

        if (is_string($value)) {
            return $value;
        }

        throw new RuntimeException('Unexpected binary column value type');
    }

    private function readTextColumn(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $content = stream_get_contents($value);

            if ($content === false) {
                throw new RuntimeException('Unable to read text column stream');
            }

            return $content;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        throw new RuntimeException('Unexpected text column value type');
    }
}
