<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\OverflowMode;
use IPTools\Range;
use PHPUnit\Framework\TestCase;

final class IPArithmeticTest extends TestCase
{
    // -------------------------------------------------------------------------
    // compareTo
    // -------------------------------------------------------------------------

    public function test_compare_to_less(): void
    {
        $this->assertSame(-1, (new IP('10.0.0.1'))->compareTo(new IP('10.0.0.2')));
    }

    public function test_compare_to_equal(): void
    {
        $this->assertSame(0, (new IP('10.0.0.1'))->compareTo(new IP('10.0.0.1')));
    }

    public function test_compare_to_greater(): void
    {
        $this->assertSame(1, (new IP('10.0.0.2'))->compareTo(new IP('10.0.0.1')));
    }

    public function test_compare_to_ipv6(): void
    {
        $this->assertSame(-1, (new IP('::1'))->compareTo(new IP('::2')));
        $this->assertSame(0, (new IP('::1'))->compareTo(new IP('::1')));
        $this->assertSame(1, (new IP('::2'))->compareTo(new IP('::1')));
    }

    public function test_compare_to_version_mismatch_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new IP('10.0.0.1'))->compareTo(new IP('::1'));
    }

    // -------------------------------------------------------------------------
    // distanceTo
    // -------------------------------------------------------------------------

    public function test_distance_to_positive(): void
    {
        $this->assertSame('1', (new IP('10.0.0.1'))->distanceTo(new IP('10.0.0.2')));
        $this->assertSame('255', (new IP('10.0.0.0'))->distanceTo(new IP('10.0.0.255')));
    }

    public function test_distance_to_zero(): void
    {
        $this->assertSame('0', (new IP('10.0.0.1'))->distanceTo(new IP('10.0.0.1')));
    }

    public function test_distance_to_negative(): void
    {
        $this->assertSame('-1', (new IP('10.0.0.2'))->distanceTo(new IP('10.0.0.1')));
    }

    public function test_distance_to_ipv6_large(): void
    {
        $distance = (new IP('::'))->distanceTo(new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'));
        $this->assertSame('340282366920938463463374607431768211455', $distance);
    }

    public function test_distance_to_version_mismatch_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new IP('10.0.0.1'))->distanceTo(new IP('::1'));
    }

    // -------------------------------------------------------------------------
    // addOffset
    // -------------------------------------------------------------------------

    public function test_add_offset_positive(): void
    {
        $ip = (new IP('10.0.0.1'))->addOffset(10);
        $this->assertSame('10.0.0.11', (string) $ip);
    }

    public function test_add_offset_negative(): void
    {
        $ip = (new IP('10.0.0.10'))->addOffset(-5);
        $this->assertSame('10.0.0.5', (string) $ip);
    }

    public function test_add_offset_zero(): void
    {
        $ip = (new IP('10.0.0.1'))->addOffset(0);
        $this->assertSame('10.0.0.1', (string) $ip);
    }

    public function test_add_offset_overflow_throws_by_default(): void
    {
        $this->expectException(OverflowException::class);
        (new IP('255.255.255.255'))->addOffset(1);
    }

    public function test_add_offset_underflow_throws_by_default(): void
    {
        $this->expectException(OverflowException::class);
        (new IP('0.0.0.0'))->addOffset(-1);
    }

    public function test_add_offset_overflow_null_mode(): void
    {
        $result = (new IP('255.255.255.255'))->addOffset(1, OverflowMode::NULL);
        $this->assertNull($result);
    }

    public function test_add_offset_underflow_null_mode(): void
    {
        $result = (new IP('0.0.0.0'))->addOffset(-1, OverflowMode::NULL);
        $this->assertNull($result);
    }

    public function test_add_offset_overflow_clamp_mode(): void
    {
        $result = (new IP('255.255.255.200'))->addOffset(100, OverflowMode::CLAMP);
        $this->assertSame('255.255.255.255', (string) $result);
    }

    public function test_add_offset_underflow_clamp_mode(): void
    {
        $result = (new IP('0.0.0.5'))->addOffset(-100, OverflowMode::CLAMP);
        $this->assertSame('0.0.0.0', (string) $result);
    }

    public function test_add_offset_overflow_wrap_mode(): void
    {
        // 255.255.255.255 + 1 wraps to 0.0.0.0
        $result = (new IP('255.255.255.255'))->addOffset(1, OverflowMode::WRAP);
        $this->assertSame('0.0.0.0', (string) $result);

        // 255.255.255.255 + 2 wraps to 0.0.0.1
        $result = (new IP('255.255.255.255'))->addOffset(2, OverflowMode::WRAP);
        $this->assertSame('0.0.0.1', (string) $result);
    }

    public function test_add_offset_underflow_wrap_mode(): void
    {
        // 0.0.0.0 - 1 wraps to 255.255.255.255
        $result = (new IP('0.0.0.0'))->addOffset(-1, OverflowMode::WRAP);
        $this->assertSame('255.255.255.255', (string) $result);
    }

    public function test_add_offset_ipv6_large_string(): void
    {
        $ip = (new IP('::'))->addOffset('340282366920938463463374607431768211455');
        $this->assertSame('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', (string) $ip);
    }

    // -------------------------------------------------------------------------
    // next
    // -------------------------------------------------------------------------

    public function test_next_basic(): void
    {
        $this->assertSame('192.168.0.2', (string) (new IP('192.168.0.1'))->next());
        $this->assertSame('192.168.1.0', (string) (new IP('192.168.0.255'))->next());
    }

    public function test_next_steps(): void
    {
        $this->assertSame('192.168.0.11', (string) (new IP('192.168.0.1'))->next(10));
        $this->assertSame('2001::ffff', (string) (new IP('2001::'))->next(65535));
    }

    public function test_next_returns_null_at_boundary(): void
    {
        $this->assertNull((new IP('255.255.255.255'))->next());
        $this->assertNull((new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'))->next());
    }

    public function test_next_zero_steps(): void
    {
        $this->assertSame('10.0.0.1', (string) (new IP('10.0.0.1'))->next(0));
    }

    public function test_next_negative_throws(): void
    {
        $this->expectException(IPTools\Exception\IpException::class);
        (new IP('10.0.0.1'))->next(-1);
    }

    public function test_next_large_string_offset_ipv6(): void
    {
        $ip = (new IP('::'))->next('340282366920938463463374607431768211454');
        $this->assertSame('ffff:ffff:ffff:ffff:ffff:ffff:ffff:fffe', (string) $ip);
    }

    // -------------------------------------------------------------------------
    // previous
    // -------------------------------------------------------------------------

    public function test_previous_basic(): void
    {
        $this->assertSame('192.168.0.0', (string) (new IP('192.168.0.1'))->previous());
        $this->assertSame('192.168.0.255', (string) (new IP('192.168.1.0'))->previous());
    }

    public function test_previous_steps(): void
    {
        $this->assertSame('192.168.1.0', (string) (new IP('192.168.1.1'))->previous(1));
        $this->assertSame('192.167.255.255', (string) (new IP('192.168.1.1'))->previous(258));
    }

    public function test_previous_returns_null_at_boundary(): void
    {
        $this->assertNull((new IP('0.0.0.0'))->previous());
        $this->assertNull((new IP('::'))->previous());
    }

    public function test_previous_negative_throws(): void
    {
        $this->expectException(IPTools\Exception\IpException::class);
        (new IP('10.0.0.1'))->previous(-1);
    }

    // -------------------------------------------------------------------------
    // shift
    // -------------------------------------------------------------------------

    public function test_shift_right_halves_value(): void
    {
        // 0.0.0.8 >> 1 = 0.0.0.4
        $this->assertSame('0.0.0.4', (string) (new IP('0.0.0.8'))->shift(1));
    }

    public function test_shift_right_to_zero(): void
    {
        // Any address >> 32 = 0.0.0.0
        $this->assertSame('0.0.0.0', (string) (new IP('255.255.255.255'))->shift(32));
    }

    public function test_shift_zero_returns_same(): void
    {
        $this->assertSame('10.0.0.1', (string) (new IP('10.0.0.1'))->shift(0));
        $this->assertSame('::1', (string) (new IP('::1'))->shift(0));
    }

    public function test_shift_left_doubles_value(): void
    {
        // 0.0.0.1 << 1 = 0.0.0.2
        $this->assertSame('0.0.0.2', (string) (new IP('0.0.0.1'))->shift(-1));
    }

    public function test_shift_left_overflow_throws_by_default(): void
    {
        $this->expectException(OverflowException::class);
        (new IP('255.255.255.255'))->shift(-1);
    }

    public function test_shift_left_overflow_null_mode(): void
    {
        $result = (new IP('255.255.255.255'))->shift(-1, OverflowMode::NULL);
        $this->assertNull($result);
    }

    public function test_shift_left_overflow_clamp_mode(): void
    {
        $result = (new IP('255.255.255.255'))->shift(-1, OverflowMode::CLAMP);
        $this->assertSame('255.255.255.255', (string) $result);
    }

    public function test_shift_left_overflow_wrap_mode(): void
    {
        // 0x80000000 << 1 = 0x100000000 which wraps to 0
        $result = (new IP('128.0.0.0'))->shift(-1, OverflowMode::WRAP);
        $this->assertSame('0.0.0.0', (string) $result);
    }

    public function test_shift_ipv6_right(): void
    {
        $ip = new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff');
        $result = $ip->shift(128);
        $this->assertSame('::', (string) $result);
    }

    // -------------------------------------------------------------------------
    // Range::addressAt
    // -------------------------------------------------------------------------

    public function test_address_at_positive_offset(): void
    {
        $range = new Range(new IP('10.0.0.0'), new IP('10.0.0.10'));
        $this->assertSame('10.0.0.0', (string) $range->addressAt(0));
        $this->assertSame('10.0.0.5', (string) $range->addressAt(5));
        $this->assertSame('10.0.0.10', (string) $range->addressAt(10));
    }

    public function test_address_at_negative_offset(): void
    {
        $range = new Range(new IP('10.0.0.0'), new IP('10.0.0.10'));
        $this->assertSame('10.0.0.10', (string) $range->addressAt(-1));
        $this->assertSame('10.0.0.9', (string) $range->addressAt(-2));
        $this->assertSame('10.0.0.0', (string) $range->addressAt(-11));
    }

    public function test_address_at_out_of_range_returns_null(): void
    {
        $range = new Range(new IP('10.0.0.0'), new IP('10.0.0.10'));
        $this->assertNull($range->addressAt(11));
        $this->assertNull($range->addressAt(-12));
    }

    public function test_address_at_or_fail_throws(): void
    {
        $range = new Range(new IP('10.0.0.0'), new IP('10.0.0.10'));
        $this->expectException(OutOfBoundsException::class);
        $range->addressAtOrFail(11);
    }

    public function test_address_at_or_fail_returns_ip(): void
    {
        $range = new Range(new IP('10.0.0.0'), new IP('10.0.0.10'));
        $this->assertSame('10.0.0.5', (string) $range->addressAtOrFail(5));
        $this->assertSame('10.0.0.10', (string) $range->addressAtOrFail(-1));
    }

    public function test_address_at_ipv6_large_string_offset(): void
    {
        $range = new Range(new IP('::'), new IP('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'));
        $ip = $range->addressAt('340282366920938463463374607431768211454');
        $this->assertSame('ffff:ffff:ffff:ffff:ffff:ffff:ffff:fffe', (string) $ip);
    }

    // -------------------------------------------------------------------------
    // Iterator still works correctly (including at max boundary)
    // -------------------------------------------------------------------------

    public function test_range_iterator_basic(): void
    {
        $range = new Range(new IP('10.0.0.1'), new IP('10.0.0.3'));
        $collected = [];
        foreach ($range as $ip) {
            $collected[] = (string) $ip;
        }
        $this->assertSame(['10.0.0.1', '10.0.0.2', '10.0.0.3'], $collected);
    }

    public function test_range_iterator_at_ipv4_max_boundary(): void
    {
        $range = new Range(new IP('255.255.255.253'), new IP('255.255.255.255'));
        $collected = [];
        foreach ($range as $ip) {
            $collected[] = (string) $ip;
        }
        $this->assertSame(['255.255.255.253', '255.255.255.254', '255.255.255.255'], $collected);
    }
}
