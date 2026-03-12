<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use IPTools\Storage\SqlRangeStorage;
use PHPUnit\Framework\TestCase;

final class SqlRangeStorageIntegrationTest extends TestCase
{
    private const MYSQL = 'mysql';

    private const PGSQL = 'pgsql';

    public function test_mysql_contains_and_metadata_roundtrip(): void
    {
        $pdo = $this->connect(self::MYSQL);
        if (! $pdo instanceof PDO) {
            $this->markTestSkipped('MySQL DSN is not configured for integration test');
        }

        $table = 'ip_ranges_it_mysql';
        $this->ensureSchema($pdo, self::MYSQL, $table);

        $storage = new SqlRangeStorage($pdo, $table);
        $storage->store(Network::parse('10.10.0.0/24'), ['engine' => self::MYSQL, 'priority' => 10]);
        $storage->store(Range::parse('10.10.1.0-10.10.1.15'), ['engine' => self::MYSQL, 'priority' => 20]);

        $this->assertTrue($storage->contains(new IP('10.10.0.42')));
        $this->assertTrue($storage->contains(new IP('10.10.1.10')));
        $this->assertFalse($storage->contains(new IP('10.10.2.1')));

        $matches = iterator_to_array($storage->findContaining(new IP('10.10.1.10')), false);
        $this->assertCount(1, $matches);
        $this->assertArrayHasKey('range', $matches[0]);
        $this->assertInstanceOf(Range::class, $matches[0]['range']);
        $this->assertSame('10.10.1.0', (string) $matches[0]['range']->getFirstIP());
        $this->assertSame('10.10.1.15', (string) $matches[0]['range']->getLastIP());
        $this->assertSame(self::MYSQL, $matches[0]['metadata']['engine'] ?? null);
        $this->assertSame(20, $matches[0]['metadata']['priority'] ?? null);
    }

    public function test_postgres_contains_and_metadata_roundtrip(): void
    {
        $pdo = $this->connect(self::PGSQL);
        if (! $pdo instanceof PDO) {
            $this->markTestSkipped('PostgreSQL DSN is not configured for integration test');
        }

        $table = 'ip_ranges_it_pgsql';
        $this->ensureSchema($pdo, self::PGSQL, $table);

        $storage = new SqlRangeStorage($pdo, $table);
        $storage->store(Network::parse('2001:db8::/126'), ['engine' => self::PGSQL, 'priority' => 10]);
        $storage->store(Range::parse('2001:db8::10-2001:db8::20'), ['engine' => self::PGSQL, 'priority' => 20]);

        $this->assertTrue($storage->contains(new IP('2001:db8::2')));
        $this->assertTrue($storage->contains(new IP('2001:db8::12')));
        $this->assertFalse($storage->contains(new IP('2001:db8::ffff')));

        $matches = iterator_to_array($storage->findContaining(new IP('2001:db8::12')), false);
        $this->assertCount(1, $matches);
        $this->assertSame(self::PGSQL, $matches[0]['metadata']['engine'] ?? null);
        $this->assertSame(20, $matches[0]['metadata']['priority'] ?? null);
    }

    private function engineDsn(string $engine): ?string
    {
        if ($engine === self::MYSQL) {
            $dsn = getenv('IPTOOLS_MYSQL_DSN');

            return is_string($dsn) && $dsn !== '' ? $dsn : null;
        }

        $dsn = getenv('IPTOOLS_PGSQL_DSN');

        return is_string($dsn) && $dsn !== '' ? $dsn : null;
    }

    private function engineUser(string $engine): string
    {
        if ($engine === self::MYSQL) {
            $user = getenv('IPTOOLS_MYSQL_USER');

            return is_string($user) ? $user : 'root';
        }

        $user = getenv('IPTOOLS_PGSQL_USER');

        return is_string($user) ? $user : 'postgres';
    }

    private function enginePass(string $engine): string
    {
        if ($engine === self::MYSQL) {
            return getenv('IPTOOLS_MYSQL_PASSWORD') !== false
                ? (string) getenv('IPTOOLS_MYSQL_PASSWORD')
                : 'root';
        }

        return getenv('IPTOOLS_PGSQL_PASSWORD') !== false
            ? (string) getenv('IPTOOLS_PGSQL_PASSWORD')
            : 'postgres';
    }

    private function connect(string $engine): ?PDO
    {
        $dsn = $this->engineDsn($engine);
        if ($dsn === null) {
            return null;
        }

        $pdo = new PDO($dsn, $this->engineUser($engine), $this->enginePass($engine));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function ensureSchema(PDO $pdo, string $engine, string $table): void
    {
        if ($engine === self::MYSQL) {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $table));
            $pdo->exec(
                sprintf(
                    'CREATE TABLE %s (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, version TINYINT NOT NULL, start_bin BINARY(16) NOT NULL, end_bin BINARY(16) NOT NULL, metadata JSON NULL, KEY idx_lookup (version, start_bin, end_bin)) ENGINE=InnoDB',
                    $table
                )
            );

            return;
        }

        $pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $table));
        $pdo->exec(
            sprintf(
                'CREATE TABLE %s (id BIGSERIAL PRIMARY KEY, version SMALLINT NOT NULL, start_bin BYTEA NOT NULL, end_bin BYTEA NOT NULL, metadata JSONB NULL)',
                $table
            )
        );
        $pdo->exec(sprintf('CREATE INDEX idx_lookup ON %s (version, start_bin, end_bin)', $table));
    }
}
