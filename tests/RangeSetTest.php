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
}
