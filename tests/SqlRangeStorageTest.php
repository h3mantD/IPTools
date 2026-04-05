<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use IPTools\Storage\AddressCodec;
use IPTools\Storage\SqlRangeStorage;
use PHPUnit\Framework\TestCase;

final class SqlRangeStorageTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for SqlRangeStorage tests');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE ip_ranges (id INTEGER PRIMARY KEY AUTOINCREMENT, version INTEGER NOT NULL, start_bin BLOB NOT NULL, end_bin BLOB NOT NULL, metadata TEXT NULL)');
        $this->pdo->exec('CREATE INDEX idx_lookup ON ip_ranges (version, start_bin, end_bin)');
    }

    public function test_address_codec_roundtrip_ipv4_and_ipv6(): void
    {
        $ipv4 = new IP('192.0.2.1');
        $encoded4 = AddressCodec::to16($ipv4);
        $this->assertSame(16, strlen($encoded4));
        $this->assertSame('192.0.2.1', (string) AddressCodec::from16($encoded4, 4));

        $ipv6 = new IP('2001:db8::1');
        $encoded6 = AddressCodec::to16($ipv6);
        $this->assertSame(16, strlen($encoded6));
        $this->assertSame('2001:db8::1', (string) AddressCodec::from16($encoded6, 6));
    }

    public function test_contains_and_boundaries_ipv4(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('192.0.2.0/24'), ['name' => 'docs-v4']);

        $this->assertTrue($storage->contains(new IP('192.0.2.0')));
        $this->assertTrue($storage->contains(new IP('192.0.2.255')));
        $this->assertFalse($storage->contains(new IP('192.0.3.0')));
    }

    public function test_contains_and_boundaries_ipv6(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Range::parse('2001:db8::1-2001:db8::ff'), ['name' => 'docs-v6']);

        $this->assertTrue($storage->contains(new IP('2001:db8::1')));
        $this->assertTrue($storage->contains(new IP('2001:db8::ff')));
        $this->assertFalse($storage->contains(new IP('2001:db8::100')));
    }

    public function test_find_containing_and_metadata_roundtrip(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'), ['policy' => 'allow']);
        $storage->store(Network::parse('10.0.0.0/25'), ['policy' => 'special', 'priority' => 10]);

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.42')), false);
        $this->assertCount(2, $matches);

        $ranges = array_map(
            static fn (array $row): string => sprintf('%s-%s', $row['range']->getFirstIP(), $row['range']->getLastIP()),
            $matches
        );
        $this->assertContains('10.0.0.0-10.0.0.127', $ranges);
        $this->assertContains('10.0.0.0-10.0.0.255', $ranges);

        $metadataPolicies = array_map(static fn (array $row): string => (string) ($row['metadata']['policy'] ?? ''), $matches);
        $this->assertContains('allow', $metadataPolicies);
        $this->assertContains('special', $metadataPolicies);
    }

    public function test_delete_and_count(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $network = Network::parse('198.51.100.0/24');

        $storage->store($network, ['tag' => 'tmp']);
        $this->assertSame(1, $storage->count());
        $this->assertTrue($storage->contains(new IP('198.51.100.10')));

        $storage->delete($network);
        $this->assertSame(0, $storage->count());
        $this->assertFalse($storage->contains(new IP('198.51.100.10')));
    }

    public function test_throws_clear_error_when_table_missing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $storage = new SqlRangeStorage($pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table "ip_ranges" is not available');

        $storage->contains(new IP('192.0.2.1'));
    }

    // -------------------------------------------------------------------------
    // count() — explicit tests
    // -------------------------------------------------------------------------

    public function test_count_empty_table(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $this->assertSame(0, $storage->count());
    }

    public function test_count_multiple_entries(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'), ['name' => 'a']);
        $storage->store(Network::parse('10.0.1.0/24'), ['name' => 'b']);
        $storage->store(Range::parse('2001:db8::1-2001:db8::ff'), ['name' => 'c']);

        $this->assertSame(3, $storage->count());
    }

    // -------------------------------------------------------------------------
    // Metadata edge cases
    // -------------------------------------------------------------------------

    public function test_store_with_null_metadata(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'));

        $this->assertTrue($storage->contains(new IP('10.0.0.1')));

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.1')), false);
        $this->assertCount(1, $matches);
        $this->assertSame([], $matches[0]['metadata']);
    }

    public function test_store_with_empty_metadata(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'), []);

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.1')), false);
        $this->assertSame([], $matches[0]['metadata']);
    }

    public function test_store_with_nested_metadata(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $metadata = [
            'org' => 'ACME',
            'tags' => ['internal', 'vpn'],
            'config' => ['priority' => 10, 'enabled' => true],
        ];
        $storage->store(Network::parse('10.0.0.0/24'), $metadata);

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.1')), false);
        $this->assertSame($metadata, $matches[0]['metadata']);
    }

    public function test_store_with_unicode_metadata(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $metadata = ['name' => '東京オフィス', 'emoji' => '🌐'];
        $storage->store(Network::parse('10.0.0.0/24'), $metadata);

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.1')), false);
        $this->assertSame($metadata, $matches[0]['metadata']);
    }

    // -------------------------------------------------------------------------
    // Delete edge cases
    // -------------------------------------------------------------------------

    public function test_delete_non_existent_range_does_not_throw(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'), ['name' => 'keep']);

        // Deleting a non-existent range should not affect existing entries
        $storage->delete(Network::parse('192.168.0.0/24'));

        $this->assertSame(1, $storage->count());
    }

    // -------------------------------------------------------------------------
    // Duplicate storage
    // -------------------------------------------------------------------------

    public function test_store_same_range_twice_creates_two_rows(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $network = Network::parse('10.0.0.0/24');

        $storage->store($network, ['tag' => 'first']);
        $storage->store($network, ['tag' => 'second']);

        $this->assertSame(2, $storage->count());

        $matches = iterator_to_array($storage->findContaining(new IP('10.0.0.1')), false);
        $this->assertCount(2, $matches);
    }

    // -------------------------------------------------------------------------
    // Contains with mixed versions
    // -------------------------------------------------------------------------

    public function test_contains_does_not_cross_versions(): void
    {
        $storage = new SqlRangeStorage($this->pdo);
        $storage->store(Network::parse('10.0.0.0/24'), ['name' => 'v4']);

        // Querying with IPv6 should not match IPv4 ranges
        $this->assertFalse($storage->contains(new IP('::ffff:10.0.0.1')));
    }

    // -------------------------------------------------------------------------
    // AddressCodec boundary values
    // -------------------------------------------------------------------------

    public function test_address_codec_boundary_ipv4(): void
    {
        $zero = new IP('0.0.0.0');
        $max = new IP('255.255.255.255');

        $this->assertSame(16, strlen(AddressCodec::to16($zero)));
        $this->assertSame('0.0.0.0', (string) AddressCodec::from16(AddressCodec::to16($zero), 4));
        $this->assertSame('255.255.255.255', (string) AddressCodec::from16(AddressCodec::to16($max), 4));
    }

    public function test_address_codec_boundary_ipv6(): void
    {
        $zero = new IP('::');
        $max = new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff');

        $this->assertSame('::', (string) AddressCodec::from16(AddressCodec::to16($zero), 6));
        $this->assertSame(
            'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff',
            (string) AddressCodec::from16(AddressCodec::to16($max), 6)
        );
    }
}
