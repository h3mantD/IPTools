<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RangeTest extends TestCase
{
    public static function getTestParseData(): array
    {
        return [
            ['127.0.0.1-127.255.255.255', ['127.0.0.1', '127.255.255.255']],
            ['127.0.0.1/24', ['127.0.0.0', '127.0.0.255']],
            ['127.*.0.0', ['127.0.0.0', '127.255.0.0']],
            ['127.255.255.0', ['127.255.255.0', '127.255.255.0']],
        ];
    }

    public static function getTestNetworksData(): array
    {
        return [
            ['192.168.1.*', ['192.168.1.0/24']],
            [
                '192.168.1.208-192.168.1.255', [
                    '192.168.1.208/28',
                    '192.168.1.224/27',
                ],
            ],
            [
                '192.168.1.0-192.168.1.191', [
                    '192.168.1.0/25',
                    '192.168.1.128/26',
                ],
            ],
            [
                '192.168.1.125-192.168.1.126', [
                    '192.168.1.125/32',
                    '192.168.1.126/32',
                ],
            ],
            [
                '49.12.11.10-49.12.11.35', [
                    '49.12.11.10/31',
                    '49.12.11.12/30',
                    '49.12.11.16/28',
                    '49.12.11.32/30',
                ],
            ],
        ];
    }

    public static function getTestContainsData(): array
    {
        return [
            ['192.168.*.*', '192.168.245.15', true],
            ['192.168.*.*', '192.169.255.255', false],

            /**
             * 10.10.45.48 --> 00001010 00001010 00101101 00110000
             * the last 0000 leads error
             */
            ['10.10.45.48/28', '10.10.45.58', true],

            ['2001:db8::/64', '2001:db8::ffff', true],
            ['2001:db8::/64', '2001:db8:ffff::', false],
        ];
    }

    public static function getTestIterationData(): array
    {
        return [
            [
                '192.168.2.0-192.168.2.7',
                [
                    '192.168.2.0',
                    '192.168.2.1',
                    '192.168.2.2',
                    '192.168.2.3',
                    '192.168.2.4',
                    '192.168.2.5',
                    '192.168.2.6',
                    '192.168.2.7',
                ],
            ],
            [
                '2001:db8::/125',
                [
                    '2001:db8::',
                    '2001:db8::1',
                    '2001:db8::2',
                    '2001:db8::3',
                    '2001:db8::4',
                    '2001:db8::5',
                    '2001:db8::6',
                    '2001:db8::7',
                ],
            ],
        ];
    }

    public static function getTestCountData(): array
    {
        return [
            ['127.0.0.0/31', 2],
            ['2001:db8::/120', 256],
        ];
    }

    public static function getPreciseCountData(): array
    {
        return [
            ['2001:db8::/64', '18446744073709551616', PHP_INT_MAX],
            ['2001:db8::/48', '1208925819614629174706176', PHP_INT_MAX],
            ['2001:db8::/128', '1', 1],
        ];
    }

    #[DataProvider('getTestParseData')]
    public function test_parse(string $data, array $expected): void
    {
        $range = Range::parse($data);

        $this->assertEquals($expected[0], $range->firstIP);
        $this->assertEquals($expected[1], $range->lastIP);
    }

    #[DataProvider('getTestNetworksData')]
    public function test_get_networks(string $data, array $expected): void
    {
        $result = [];

        foreach (Range::parse($data)->getNetworks() as $network) {
            $result[] = (string) $network;
        }

        $this->assertEquals($expected, $result);
    }

    public function test_iterate_networks(): void
    {
        $result = [];
        foreach (Range::parse('192.168.1.208-192.168.1.255')->iterateNetworks() as $network) {
            $result[] = (string) $network;
        }

        $this->assertSame(['192.168.1.208/28', '192.168.1.224/27'], $result);
    }

    public function test_network_selectors(): void
    {
        $range = Range::parse('49.12.11.10-49.12.11.35');

        $this->assertSame('49.12.11.10/31', (string) $range->getFirstNetwork());
        $this->assertSame('49.12.11.32/30', (string) $range->getLastNetwork());
        $this->assertSame('49.12.11.16/28', (string) $range->getNthNetwork(2));
        $this->assertNull($range->getNthNetwork(10));
    }

    public function test_get_nth_network_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Network index must be non-negative');

        Range::parse('192.168.1.*')->getNthNetwork(-1);
    }

    public function test_network_selectors_single_network_range(): void
    {
        $range = Range::parse('2001:db8::/64');

        $this->assertSame('2001:db8::/64', (string) $range->getFirstNetwork());
        $this->assertSame('2001:db8::/64', (string) $range->getLastNetwork());

        $nthNetwork = $range->getNthNetwork(0);
        $this->assertInstanceOf(Network::class, $nthNetwork);
        $this->assertSame('2001:db8::/64', (string) $nthNetwork);
    }

    #[DataProvider('getTestContainsData')]
    public function test_contains(string $data, string $find, bool $expected): void
    {
        $this->assertEquals($expected, Range::parse($data)->contains(new IP($find)));
    }

    #[DataProvider('getTestIterationData')]
    public function test_range_iteration(string $data, array $expected): void
    {
        $result = [];
        foreach (Range::parse($data) as $ip) {
            $result[] = (string) $ip;
        }

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('getTestCountData')]
    public function test_count(string $data, int $expected): void
    {
        $this->assertEquals($expected, count(Range::parse($data)));
    }

    #[DataProvider('getPreciseCountData')]
    public function test_count_precise(string $data, string $expectedPrecise, int $expectedCount): void
    {
        $range = Range::parse($data);

        $this->assertSame($expectedPrecise, $range->getCountPrecise());
        $this->assertSame($expectedCount, count($range));
    }

    public function test_constructor_rejects_mixed_ip_versions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Last IP version does not match first IP version');

        new Range(IP::parse('127.0.0.1'), IP::parse('2001:db8::1'));
    }

    public function test_address_at_rejects_non_integer_offset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be an integer string');

        Range::parse('10.0.0.1-10.0.0.10')->addressAt('1.5');
    }
}
