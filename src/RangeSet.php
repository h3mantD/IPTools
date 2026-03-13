<?php

declare(strict_types=1);

namespace IPTools;

use Countable;

/**
 * Canonical set of disjoint/merged IP ranges.
 */
final class RangeSet implements Countable
{
    /**
     * @var array<string, array<int, Range>>
     */
    private array $rangesByVersion = [
        IP::IP_V4 => [],
        IP::IP_V6 => [],
    ];

    /**
     * @param  iterable<int, Range|Network|IP|string>  $ranges
     */
    public function __construct(iterable $ranges = [])
    {
        foreach ($ranges as $range) {
            $parsed = $this->parseItem($range);
            $version = $parsed->getFirstIP()->getVersion();
            $this->rangesByVersion[$version][] = $parsed;
        }

        $this->rangesByVersion[IP::IP_V4] = $this->normalize($this->rangesByVersion[IP::IP_V4]);
        $this->rangesByVersion[IP::IP_V6] = $this->normalize($this->rangesByVersion[IP::IP_V6]);
    }

    /**
     * @param  iterable<int, Range|Network|IP|string>  $ranges
     */
    public static function from(iterable $ranges): self
    {
        return new self($ranges);
    }

    /**
     * @param  RangeSet|iterable<int, Range|Network|IP|string>|Range|Network|IP|string  $other
     */
    public function union(self|iterable|Range|Network|IP|string $other): self
    {
        $left = $this->getRanges();
        $right = $this->parseInput($other);
        /** @var array<int, Range|Network|IP|string> $merged */
        $merged = array_values(array_merge($left, $right));

        return new self($merged);
    }

    /**
     * @param  RangeSet|iterable<int, Range|Network|IP|string>|Range|Network|IP|string  $other
     */
    public function intersect(self|iterable|Range|Network|IP|string $other): self
    {
        $otherSet = $other instanceof self ? $other : new self($this->parseInput($other));
        $result = [];

        foreach ([IP::IP_V4, IP::IP_V6] as $version) {
            $left = $this->rangesByVersion[$version];
            $right = $otherSet->rangesByVersion[$version];

            $i = 0;
            $j = 0;
            while ($i < count($left) && $j < count($right)) {
                $a = $left[$i];
                $b = $right[$j];

                if (self::compareIp($a->getLastIP(), $b->getFirstIP()) < 0) {
                    $i++;

                    continue;
                }

                if (self::compareIp($b->getLastIP(), $a->getFirstIP()) < 0) {
                    $j++;

                    continue;
                }

                $start = self::compareIp($a->getFirstIP(), $b->getFirstIP()) >= 0 ? $a->getFirstIP() : $b->getFirstIP();
                $end = self::compareIp($a->getLastIP(), $b->getLastIP()) <= 0 ? $a->getLastIP() : $b->getLastIP();
                $result[] = new Range($start, $end);

                if (self::compareIp($a->getLastIP(), $b->getLastIP()) < 0) {
                    $i++;
                } else {
                    $j++;
                }
            }
        }

        return new self($result);
    }

    /**
     * @param  RangeSet|iterable<int, Range|Network|IP|string>|Range|Network|IP|string  $other
     */
    public function subtract(self|iterable|Range|Network|IP|string $other): self
    {
        $otherSet = $other instanceof self ? $other : new self($this->parseInput($other));
        $result = [];

        foreach ([IP::IP_V4, IP::IP_V6] as $version) {
            $remaining = $this->rangesByVersion[$version];
            $subtractors = $otherSet->rangesByVersion[$version];

            foreach ($subtractors as $subtractor) {
                $nextRemaining = [];
                foreach ($remaining as $range) {
                    $pieces = $this->subtractOne($range, $subtractor);
                    foreach ($pieces as $piece) {
                        $nextRemaining[] = $piece;
                    }
                }

                $remaining = $nextRemaining;
                if ($remaining === []) {
                    break;
                }
            }

            foreach ($remaining as $range) {
                $result[] = $range;
            }
        }

        return new self($result);
    }

    public function contains(IP $ip): bool
    {
        foreach ($this->rangesByVersion[$ip->getVersion()] as $range) {
            if ($range->contains($ip)) {
                return true;
            }
        }

        return false;
    }

    public function containsRange(Range|Network|IP|string $candidate): bool
    {
        $range = $this->parseItem($candidate);
        foreach ($this->rangesByVersion[$range->getFirstIP()->getVersion()] as $container) {
            if ($container->contains($range)) {
                return true;
            }
        }

        return false;
    }

    public function overlaps(Range|Network|IP|string $candidate): bool
    {
        $range = $this->parseItem($candidate);
        $version = $range->getFirstIP()->getVersion();

        foreach ($this->rangesByVersion[$version] as $existing) {
            if (self::compareIp($existing->getLastIP(), $range->getFirstIP()) < 0) {
                continue;
            }

            return self::compareIp($existing->getFirstIP(), $range->getLastIP()) <= 0;
        }

        return false;
    }

    /**
     * @return Network[]
     */
    public function toCidrs(): array
    {
        $networks = [];
        foreach ($this->getRanges() as $range) {
            foreach ($range->getNetworks() as $network) {
                $networks[] = $network;
            }
        }

        return $networks;
    }

    /**
     * @return Range[]
     */
    public function getRanges(): array
    {
        return array_merge($this->rangesByVersion[IP::IP_V4], $this->rangesByVersion[IP::IP_V6]);
    }

    public function count(): int
    {
        return count($this->rangesByVersion[IP::IP_V4]) + count($this->rangesByVersion[IP::IP_V6]);
    }

    private static function compareIp(IP $a, IP $b): int
    {
        return strcmp($a->inAddr(), $b->inAddr());
    }

    /**
     * @param  array<int, Range>  $ranges
     * @return array<int, Range>
     */
    private function normalize(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        usort($ranges, static function (Range $a, Range $b): int {
            $start = self::compareIp($a->getFirstIP(), $b->getFirstIP());
            if ($start !== 0) {
                return $start;
            }

            return self::compareIp($a->getLastIP(), $b->getLastIP());
        });

        $normalized = [];
        foreach ($ranges as $range) {
            if ($normalized === []) {
                $normalized[] = $range;

                continue;
            }

            $last = $normalized[count($normalized) - 1];

            if (! $this->touchesOrOverlaps($last, $range)) {
                $normalized[] = $range;

                continue;
            }

            $mergedLast = self::compareIp($last->getLastIP(), $range->getLastIP()) >= 0
                ? $last->getLastIP()
                : $range->getLastIP();

            $normalized[count($normalized) - 1] = new Range($last->getFirstIP(), $mergedLast);
        }

        return array_values($normalized);
    }

    private function touchesOrOverlaps(Range $left, Range $right): bool
    {
        if (self::compareIp($right->getFirstIP(), $left->getLastIP()) <= 0) {
            return true;
        }

        $next = $left->getLastIP()->next();

        return $next instanceof IP
            && self::compareIp($next, $right->getFirstIP()) === 0;
    }

    /**
     * @return Range[]
     */
    private function subtractOne(Range $range, Range $subtractor): array
    {
        if (self::compareIp($subtractor->getLastIP(), $range->getFirstIP()) < 0
            || self::compareIp($subtractor->getFirstIP(), $range->getLastIP()) > 0
        ) {
            return [$range];
        }

        $pieces = [];

        if (self::compareIp($subtractor->getFirstIP(), $range->getFirstIP()) > 0) {
            $leftEnd = $subtractor->getFirstIP()->previous();
            if ($leftEnd instanceof IP) {
                $pieces[] = new Range($range->getFirstIP(), $leftEnd);
            }
        }

        if (self::compareIp($subtractor->getLastIP(), $range->getLastIP()) < 0) {
            $rightStart = $subtractor->getLastIP()->next();
            if ($rightStart instanceof IP) {
                $pieces[] = new Range($rightStart, $range->getLastIP());
            }
        }

        return $pieces;
    }

    /**
     * @param  RangeSet|iterable<int, Range|Network|IP|string>|Range|Network|IP|string  $input
     * @return array<int, Range>
     */
    private function parseInput(self|iterable|Range|Network|IP|string $input): array
    {
        if ($input instanceof self) {
            return array_values($input->getRanges());
        }

        if ($input instanceof Range || $input instanceof Network || $input instanceof IP || is_string($input)) {
            return [$this->parseItem($input)];
        }

        $ranges = [];
        foreach ($input as $item) {
            $ranges[] = $this->parseItem($item);
        }

        return $ranges;
    }

    private function parseItem(Range|Network|IP|string $item): Range
    {
        if ($item instanceof Range) {
            return new Range($item->getFirstIP(), $item->getLastIP());
        }

        if ($item instanceof Network) {
            return new Range($item->getFirstIP(), $item->getLastIP());
        }

        if ($item instanceof IP) {
            return new Range($item, $item);
        }

        return Range::parse($item);
    }
}
