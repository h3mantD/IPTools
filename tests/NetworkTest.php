<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\Network;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NetworkTest extends TestCase
{
    public static function getTestParseData(): array
    {
        return [
            ['192.168.0.54/24', '192.168.0.0/24'],
            ['2001::2001:2001/32', '2001::/32'],
            ['127.168.0.1 255.255.255.255', '127.168.0.1/32'],
            ['1234::1234', '1234::1234/128'],
        ];
    }

    public static function getPrefixData(): array
    {
        return [
            ['24', IP::IP_V4, IP::parse('255.255.255.0')],
            ['32', IP::IP_V4, IP::parse('255.255.255.255')],
            ['64', IP::IP_V6, IP::parse('ffff:ffff:ffff:ffff::')],
            ['128', IP::IP_V6, IP::parse('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff')],
        ];
    }

    public static function getInvalidPrefixData(): array
    {
        return [
            ['-1', IP::IP_V4],
            ['33', IP::IP_V4],
            ['-1', IP::IP_V6],
            ['129', IP::IP_V6],
        ];
    }

    public static function getHostsData(): array
    {
        return [
            [
                '192.0.2.0/29',
                [
                    '192.0.2.1',
                    '192.0.2.2',
                    '192.0.2.3',
                    '192.0.2.4',
                    '192.0.2.5',
                    '192.0.2.6',
                ],
            ],
        ];
    }

    public static function getExcludeData(): array
    {
        return [
            [
                '192.0.2.0/28', '192.0.2.1/32',
                [
                    '192.0.2.0/32',
                    '192.0.2.2/31',
                    '192.0.2.4/30',
                    '192.0.2.8/29',
                ],
            ],
            ['192.0.2.2/32', '192.0.2.2/32', []],
        ];
    }

    public static function getExcludeExceptionData(): array
    {
        return [
            ['192.0.2.0/28', '192.0.3.0/24'],
            ['192.0.2.2/32', '192.0.2.3/32'],
            ['192.0.2.0/28', '192.0.2.0/27'],
        ];
    }

    public static function getMoveToData(): array
    {
        return [
            [
                '192.168.0.0/22', '24',
                [
                    '192.168.0.0/24',
                    '192.168.1.0/24',
                    '192.168.2.0/24',
                    '192.168.3.0/24',
                ],
            ],
            [
                '192.168.2.0/24', '25',
                [
                    '192.168.2.0/25',
                    '192.168.2.128/25',
                ],
            ],
            [
                '192.168.2.0/30', '32',
                [
                    '192.168.2.0/32',
                    '192.168.2.1/32',
                    '192.168.2.2/32',
                    '192.168.2.3/32',
                ],
            ],
            [
                '240.0.0.0/4', '5',
                [
                    '240.0.0.0/5',
                    '248.0.0.0/5',
                ],
            ],
            [
                '248.0.0.0/5', '6',
                [
                    '248.0.0.0/6',
                    '252.0.0.0/6',
                ],
            ],
            [
                '224.0.0.0/3', '4',
                [
                    '224.0.0.0/4',
                    '240.0.0.0/4',
                ],
            ],
        ];
    }

    public static function getMoveToExceptionData(): array
    {
        return [
            ['192.168.0.0/22', '22'],
            ['192.168.0.0/22', '21'],
            ['192.168.0.0/22', '33'],
            ['192.168.0.0/22', 'prefixLength'],
            ['192.168.0.0/22', '24abc'],
        ];
    }

    public static function getTestIterationData(): array
    {
        return [
            [
                '192.168.2.0/29',
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

    public static function getSummarizeData(): array
    {
        return [
            [
                ['192.168.0.0/25', '192.168.0.128/25'],
                ['192.168.0.0/24'],
            ],
            [
                ['10.0.0.0/24', '10.0.0.0/25', '10.0.0.128/25', '10.0.1.0/24'],
                ['10.0.0.0/23'],
            ],
            [
                ['2001:db8::/65', '2001:db8:0:0:8000::/65'],
                ['2001:db8::/64'],
            ],
        ];
    }

    public function test_constructor(): void
    {
        $ipv4 = new IP('127.0.0.1');
        $ipv4Netmask = new IP('255.255.255.0');

        $ipv6 = new IP('2001::');
        $ipv6Netmask = new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff::');

        $ipv4Network = new Network($ipv4, $ipv4Netmask);
        $ipv6Network = new Network($ipv6, $ipv6Netmask);

        $this->assertEquals('127.0.0.0/24', (string) $ipv4Network);
        $this->assertEquals('2001::/112', (string) $ipv6Network);
    }

    public function test_properties(): void
    {
        $network = Network::parse('127.0.0.1/24');

        $network->ip = new IP('192.0.0.2');

        $this->assertEquals('192.0.0.2', $network->ip);
        $this->assertEquals('192.0.0.0/24', (string) $network);
        $this->assertEquals('0.0.0.255', (string) $network->wildcard);
        $this->assertEquals('192.0.0.0', (string) $network->firstIP);
        $this->assertEquals('192.0.0.255', (string) $network->lastIP);
    }

    #[DataProvider('getTestParseData')]
    public function test_parse(string $data, string $expected): void
    {
        $this->assertEquals($expected, (string) Network::parse($data));
    }

    public function test_parse_wrong_network(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid IP address format');

        Network::parse('10.0.0.0/24 abc');
    }

    #[DataProvider('getPrefixData')]
    public function test_prefix2_mask(string $prefix, string $version, IP $mask): void
    {
        $this->assertEquals($mask, Network::prefix2netmask($prefix, $version));
    }

    public function test_prefix2_mask_wrong_ip_version(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wrong IP version');

        Network::prefix2netmask('128', 'ip_version');
    }

    #[DataProvider('getInvalidPrefixData')]
    public function test_prefix2_mask_invalid_prefix(string $prefix, string $version): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid prefix length');

        Network::prefix2netmask($prefix, $version);
    }

    #[DataProvider('getHostsData')]
    public function test_hosts(string $data, array $expected): void
    {
        $result = [];

        foreach (Network::parse($data)->hosts as $ip) {
            $result[] = (string) $ip;
        }

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('getExcludeData')]
    public function test_exclude(string $data, string $exclude, array $expected): void
    {
        $result = [];

        foreach (Network::parse($data)->exclude($exclude) as $network) {
            $result[] = (string) $network;
        }

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('getExcludeExceptionData')]
    public function test_exclude_exception(string $data, string $exclude): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Exclude subnet not within target network');

        Network::parse($data)->exclude($exclude);
    }

    #[DataProvider('getMoveToData')]
    public function test_move_to(string $network, string $prefixLength, array $expected): void
    {
        $result = [];

        foreach (Network::parse($network)->moveTo($prefixLength) as $sub_network) {
            $result[] = (string) $sub_network;
        }

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('getMoveToExceptionData')]
    public function test_move_to_exception(string $network, string $prefixLength): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid prefix length');

        Network::parse($network)->moveTo($prefixLength);
    }

    #[DataProvider('getTestIterationData')]
    public function test_network_iteration(string $data, array $expected): void
    {
        $result = [];

        foreach (Network::parse($data) as $ip) {
            $result[] = (string) $ip;
        }

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('getTestCountData')]
    public function test_count(string $data, int $expected): void
    {
        $this->assertCount($expected, Network::parse($data));
    }

    #[DataProvider('getPreciseCountData')]
    public function test_count_precise(string $data, string $expectedPrecise, int $expectedCount): void
    {
        $network = Network::parse($data);

        $this->assertSame($expectedPrecise, $network->getCountPrecise());
        $this->assertSame($expectedCount, count($network));
    }

    #[DataProvider('getSummarizeData')]
    public function test_summarize(array $data, array $expected): void
    {
        $result = [];
        foreach (Network::summarize($data) as $network) {
            $result[] = (string) $network;
        }

        $this->assertSame($expected, $result);
    }

    public function test_network_helper_aliases(): void
    {
        $network = Network::parse('192.0.2.130/24');

        $this->assertSame('192.0.2.0', (string) $network->networkAddress());
        $this->assertSame('192.0.2.255', (string) $network->broadcastAddress());
    }

    public function test_host_boundaries_and_usable_counts_ipv4(): void
    {
        $normal = Network::parse('192.0.2.0/24');
        $this->assertSame('192.0.2.1', (string) $normal->firstHost());
        $this->assertSame('192.0.2.254', (string) $normal->lastHost());
        $this->assertSame(254, $normal->usableHostCount());

        $pointToPoint = Network::parse('192.0.2.0/31');
        $this->assertTrue($pointToPoint->isPointToPoint());
        $this->assertSame('192.0.2.0', (string) $pointToPoint->firstHost());
        $this->assertSame('192.0.2.1', (string) $pointToPoint->lastHost());
        $this->assertSame(2, $pointToPoint->usableHostCount());

        $single = Network::parse('192.0.2.10/32');
        $this->assertFalse($single->isPointToPoint());
        $this->assertSame('192.0.2.10', (string) $single->firstHost());
        $this->assertSame('192.0.2.10', (string) $single->lastHost());
        $this->assertSame(1, $single->usableHostCount());
    }

    public function test_host_boundaries_and_usable_counts_ipv6(): void
    {
        $network = Network::parse('2001:db8::/126');
        $this->assertSame('2001:db8::', (string) $network->firstHost());
        $this->assertSame('2001:db8::3', (string) $network->lastHost());
        $this->assertSame('4', (string) $network->usableHostCount());

        $pointToPoint = Network::parse('2001:db8::/127');
        $this->assertTrue($pointToPoint->isPointToPoint());
    }

    public function test_contains_helpers(): void
    {
        $network = Network::parse('10.0.0.0/24');

        $this->assertTrue($network->containsIP('10.0.0.42'));
        $this->assertFalse($network->containsIP('10.0.1.1'));

        $this->assertTrue($network->containsRange('10.0.0.10-10.0.0.20'));
        $this->assertTrue($network->containsRange(Network::parse('10.0.0.0/25')));
        $this->assertFalse($network->containsRange('10.0.1.0/24'));
        $this->assertFalse($network->containsRange('2001:db8::/64'));
    }

    public function test_next_previous_subnet(): void
    {
        $network = Network::parse('10.0.0.0/24');

        $next = $network->nextSubnet();
        $previous = $network->previousSubnet();

        $this->assertInstanceOf(Network::class, $next);
        $this->assertInstanceOf(Network::class, $previous);
        $this->assertSame('10.0.1.0/24', (string) $next);
        $this->assertSame('9.255.255.0/24', (string) $previous);

        $this->assertNull(Network::parse('0.0.0.0/0')->previousSubnet());
        $this->assertNull(Network::parse('255.255.255.0/24')->nextSubnet());
    }
}
