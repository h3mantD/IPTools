<?php

declare(strict_types=1);

namespace IPTools;

use IPTools\Exception\IpToolsException;
use RuntimeException;

/**
 * Shared Iterator implementation for Network and Range.
 *
 * Both classes iterate over a contiguous IP address span defined by
 * getFirstIP() and getLastIP(). This trait provides the common
 * current/key/next/rewind/valid logic, avoiding 40+ lines of duplication.
 *
 * Requires the using class to implement getFirstIP() and getLastIP().
 */
trait IPIteratorTrait
{
    private int $position = 0;

    private ?IP $currentIP = null;

    public function current(): IP
    {
        if (! $this->currentIP instanceof IP) {
            $ip = $this->getFirstIP()->next($this->position);
            if (! $ip instanceof IP) {
                throw new class('Iterator position is out of range') extends RuntimeException implements IpToolsException {};
            }

            $this->currentIP = $ip;
        }

        return $this->currentIP;
    }

    public function key(): int
    {
        return $this->position;
    }

    /**
     * Advance the iterator. If the current IP is at the address-space
     * boundary, next() on it returns null — which signals exhaustion
     * in valid().
     */
    public function next(): void
    {
        $this->position++;

        if ($this->currentIP instanceof IP) {
            $this->currentIP = $this->currentIP->next();
        }
    }

    public function rewind(): void
    {
        $this->position = 0;
        $this->currentIP = null;
    }

    public function valid(): bool
    {
        // next() set currentIP to null when the address-space boundary was reached
        if (! $this->currentIP instanceof IP && $this->position > 0) {
            return false;
        }

        return strcmp($this->current()->inAddr(), $this->getLastIP()->inAddr()) <= 0;
    }
}
