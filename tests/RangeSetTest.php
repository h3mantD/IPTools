<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\RangeSet;
use PHPUnit\Framework\TestCase;

final class RangeSetTest extends TestCase
{
    public function test_normalizes_overlapping_and_adjacent_ranges(): void
    {
        $set = new RangeSet([
            '10.0.0.0-10.0.0.10',
            '10.0.0.11-10.0.0.20',
            '10.0.0.5-10.0.0.8',
        ]);

        $ranges = $set->getRanges();
        $this->assertCount(1, $ranges);
        $this->assertSame('10.0.0.0', (string) $ranges[0]->getFirstIP());
        $this->assertSame('10.0.0.20', (string) $ranges[0]->getLastIP());
    }

    public function test_union_of_disjoint_sets(): void
    {
        $left = new RangeSet(['10.0.0.0-10.0.0.10']);
        $right = new RangeSet(['10.0.0.20-10.0.0.30']);

        $union = $left->union($right);
        $this->assertCount(2, $union->getRanges());
        $this->assertTrue($union->contains(new IP('10.0.0.25')));
    }

    public function test_intersection_only_keeps_common_portion(): void
    {
        $left = new RangeSet(['10.0.0.0-10.0.0.20']);
        $right = new RangeSet(['10.0.0.10-10.0.0.30']);

        $intersection = $left->intersect($right);
        $ranges = $intersection->getRanges();

        $this->assertCount(1, $ranges);
        $this->assertSame('10.0.0.10', (string) $ranges[0]->getFirstIP());
        $this->assertSame('10.0.0.20', (string) $ranges[0]->getLastIP());
    }

    public function test_subtract_splits_range_when_middle_removed(): void
    {
        $set = new RangeSet(['10.0.0.0-10.0.0.20']);

        $result = $set->subtract('10.0.0.5-10.0.0.10');
        $ranges = $result->getRanges();

        $this->assertCount(2, $ranges);
        $this->assertSame('10.0.0.0', (string) $ranges[0]->getFirstIP());
        $this->assertSame('10.0.0.4', (string) $ranges[0]->getLastIP());
        $this->assertSame('10.0.0.11', (string) $ranges[1]->getFirstIP());
        $this->assertSame('10.0.0.20', (string) $ranges[1]->getLastIP());
    }

    public function test_mixed_versions_are_isolated(): void
    {
        $set = new RangeSet([
            '10.0.0.0/24',
            '2001:db8::/126',
        ]);

        $this->assertTrue($set->contains(new IP('10.0.0.42')));
        $this->assertTrue($set->contains(new IP('2001:db8::2')));

        $subtracted = $set->subtract('10.0.0.0/25');
        $this->assertFalse($subtracted->contains(new IP('10.0.0.42')));
        $this->assertTrue($subtracted->contains(new IP('10.0.0.200')));
        $this->assertTrue($subtracted->contains(new IP('2001:db8::2')));
    }

    public function test_contains_range_and_overlap_checks(): void
    {
        $set = new RangeSet(['10.0.0.0/24']);

        $this->assertTrue($set->containsRange('10.0.0.10-10.0.0.20'));
        $this->assertFalse($set->containsRange('10.0.1.0/24'));

        $this->assertTrue($set->overlaps('10.0.0.200-10.0.1.1'));
        $this->assertFalse($set->overlaps('10.0.2.0/24'));
    }

    public function test_to_cidrs_returns_minimized_networks(): void
    {
        $set = new RangeSet(['10.0.0.0-10.0.0.255']);
        $cidrs = $set->toCidrs();

        $this->assertCount(1, $cidrs);
        $this->assertSame('10.0.0.0/24', (string) $cidrs[0]);
    }

    // -------------------------------------------------------------------------
    // Empty RangeSet
    // -------------------------------------------------------------------------

    public function test_empty_set_count(): void
    {
        $set = new RangeSet([]);

        $this->assertCount(0, $set);
        $this->assertSame([], $set->getRanges());
    }

    public function test_empty_set_contains(): void
    {
        $set = new RangeSet([]);

        $this->assertFalse($set->contains(new IP('10.0.0.1')));
        $this->assertFalse($set->containsRange('10.0.0.0/24'));
        $this->assertFalse($set->overlaps('10.0.0.0/24'));
    }

    public function test_empty_set_union(): void
    {
        $empty = new RangeSet([]);
        $other = new RangeSet(['10.0.0.0-10.0.0.10']);

        $union = $empty->union($other);
        $this->assertCount(1, $union->getRanges());
        $this->assertTrue($union->contains(new IP('10.0.0.5')));
    }

    public function test_empty_set_intersect(): void
    {
        $empty = new RangeSet([]);
        $other = new RangeSet(['10.0.0.0-10.0.0.10']);

        $intersection = $empty->intersect($other);
        $this->assertSame([], $intersection->getRanges());
    }

    public function test_empty_set_subtract(): void
    {
        $set = new RangeSet(['10.0.0.0-10.0.0.10']);
        $empty = new RangeSet([]);

        // subtracting empty changes nothing
        $result = $set->subtract($empty);
        $this->assertCount(1, $result->getRanges());

        // subtracting from empty stays empty
        $result = $empty->subtract($set);
        $this->assertSame([], $result->getRanges());
    }

    // -------------------------------------------------------------------------
    // IPv6 set operations
    // -------------------------------------------------------------------------

    public function test_ipv6_union(): void
    {
        $left = new RangeSet(['2001:db8::-2001:db8::ff']);
        $right = new RangeSet(['2001:db8::f0-2001:db8::1ff']);

        $union = $left->union($right);
        $ranges = $union->getRanges();

        $this->assertCount(1, $ranges);
        $this->assertSame('2001:db8::', (string) $ranges[0]->getFirstIP());
        $this->assertSame('2001:db8::1ff', (string) $ranges[0]->getLastIP());
    }

    public function test_ipv6_intersect(): void
    {
        $left = new RangeSet(['2001:db8::-2001:db8::ff']);
        $right = new RangeSet(['2001:db8::80-2001:db8::1ff']);

        $intersection = $left->intersect($right);
        $ranges = $intersection->getRanges();

        $this->assertCount(1, $ranges);
        $this->assertSame('2001:db8::80', (string) $ranges[0]->getFirstIP());
        $this->assertSame('2001:db8::ff', (string) $ranges[0]->getLastIP());
    }

    public function test_ipv6_subtract(): void
    {
        $set = new RangeSet(['2001:db8::-2001:db8::ff']);

        $result = $set->subtract('2001:db8::10-2001:db8::20');
        $ranges = $result->getRanges();

        $this->assertCount(2, $ranges);
        $this->assertSame('2001:db8::', (string) $ranges[0]->getFirstIP());
        $this->assertSame('2001:db8::f', (string) $ranges[0]->getLastIP());
        $this->assertSame('2001:db8::21', (string) $ranges[1]->getFirstIP());
        $this->assertSame('2001:db8::ff', (string) $ranges[1]->getLastIP());
    }

    public function test_ipv6_contains_and_overlaps(): void
    {
        $set = new RangeSet(['2001:db8::/120']);

        $this->assertTrue($set->contains(new IP('2001:db8::42')));
        $this->assertFalse($set->contains(new IP('2001:db8::ffff')));
        $this->assertTrue($set->overlaps('2001:db8::f0-2001:db8::1ff'));
        $this->assertFalse($set->overlaps('2001:db8::200-2001:db8::2ff'));
    }

    // -------------------------------------------------------------------------
    // from() factory
    // -------------------------------------------------------------------------

    public function test_from_factory(): void
    {
        $set = RangeSet::from(['10.0.0.0-10.0.0.10', '10.0.0.5-10.0.0.20']);
        $ranges = $set->getRanges();

        $this->assertCount(1, $ranges);
        $this->assertSame('10.0.0.0', (string) $ranges[0]->getFirstIP());
        $this->assertSame('10.0.0.20', (string) $ranges[0]->getLastIP());
    }

    // -------------------------------------------------------------------------
    // Intersect disjoint sets returns empty
    // -------------------------------------------------------------------------

    public function test_intersect_disjoint_returns_empty(): void
    {
        $left = new RangeSet(['10.0.0.0-10.0.0.10']);
        $right = new RangeSet(['10.0.0.20-10.0.0.30']);

        $this->assertSame([], $left->intersect($right)->getRanges());
    }

    // -------------------------------------------------------------------------
    // Subtract with no overlap returns original
    // -------------------------------------------------------------------------

    public function test_subtract_no_overlap_returns_original(): void
    {
        $set = new RangeSet(['10.0.0.0-10.0.0.10']);
        $result = $set->subtract('10.0.0.20-10.0.0.30');
        $ranges = $result->getRanges();

        $this->assertCount(1, $ranges);
        $this->assertSame('10.0.0.0', (string) $ranges[0]->getFirstIP());
        $this->assertSame('10.0.0.10', (string) $ranges[0]->getLastIP());
    }

    // -------------------------------------------------------------------------
    // Subtract resulting in empty set
    // -------------------------------------------------------------------------

    public function test_subtract_entire_range_returns_empty(): void
    {
        $set = new RangeSet(['10.0.0.0-10.0.0.10']);
        $result = $set->subtract('10.0.0.0-10.0.0.255');

        $this->assertSame([], $result->getRanges());
    }

    // -------------------------------------------------------------------------
    // toCidrs edge cases
    // -------------------------------------------------------------------------

    public function test_to_cidrs_non_aligned_range(): void
    {
        $set = new RangeSet(['10.0.0.1-10.0.0.2']);
        $cidrs = $set->toCidrs();

        $this->assertCount(2, $cidrs);
        $this->assertSame('10.0.0.1/32', (string) $cidrs[0]);
        $this->assertSame('10.0.0.2/32', (string) $cidrs[1]);
    }

    public function test_to_cidrs_single_ip(): void
    {
        $set = new RangeSet(['10.0.0.1']);
        $cidrs = $set->toCidrs();

        $this->assertCount(1, $cidrs);
        $this->assertSame('10.0.0.1/32', (string) $cidrs[0]);
    }
}
