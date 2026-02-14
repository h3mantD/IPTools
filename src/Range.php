<?php

declare(strict_types=1);

namespace IPTools;

use Countable;
use IPTools\Exception\RangeException;
use Iterator;

/**
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 *
 * @implements Iterator<int, IP>
 */
class Range implements Countable, Iterator
{
    use PropertyTrait;

    private ?IP $firstIP = null;

    private ?IP $lastIP = null;

    private int $position = 0;

    private ?IP $currentIP = null;

    /**
     * @throws RangeException
     */
    public function __construct(IP $firstIP, IP $lastIP)
    {
        $this->setFirstIP($firstIP);
        $this->setLastIP($lastIP);
    }

    public static function parse(string $data): self
    {
        if (str_contains($data, '/') || str_contains($data, ' ')) {
            $network = Network::parse($data);
            $firstIP = $network->getFirstIP();
            $lastIP = $network->getLastIP();
        } elseif (str_contains($data, '*')) {
            $firstIP = IP::parse(str_replace('*', '0', $data));
            $lastIP = IP::parse(str_replace('*', '255', $data));
        } elseif (str_contains($data, '-')) {
            [$first, $last] = explode('-', $data, 2);
            $firstIP = IP::parse($first);
            $lastIP = IP::parse($last);
        } else {
            $firstIP = IP::parse($data);
            $lastIP = clone $firstIP;
        }

        return new self($firstIP, $lastIP);
    }

    public function contains(IP|Network|self $find): bool
    {
        $firstIP = $this->getFirstIP();
        $lastIP = $this->getLastIP();
        if ($find instanceof IP) {
            return (strcmp($find->inAddr(), $firstIP->inAddr()) >= 0)
                && (strcmp($find->inAddr(), $lastIP->inAddr()) <= 0);
        }

        return (strcmp($find->getFirstIP()->inAddr(), $firstIP->inAddr()) >= 0)
            && (strcmp($find->getLastIP()->inAddr(), $lastIP->inAddr()) <= 0);
    }

    /**
     * @throws RangeException
     */
    public function setFirstIP(IP $ip): void
    {
        if ($this->lastIP instanceof IP && $ip->getVersion() !== $this->lastIP->getVersion()) {
            throw new RangeException('First IP version does not match last IP version');
        }

        if ($this->lastIP instanceof IP && strcmp($ip->inAddr(), $this->lastIP->inAddr()) > 0) {
            throw new RangeException('First IP is greater than second');
        }

        $this->firstIP = $ip;
    }

    /**
     * @throws RangeException
     */
    public function setLastIP(IP $ip): void
    {
        if ($this->firstIP instanceof IP && $ip->getVersion() !== $this->firstIP->getVersion()) {
            throw new RangeException('Last IP version does not match first IP version');
        }

        if ($this->firstIP instanceof IP && strcmp($ip->inAddr(), $this->firstIP->inAddr()) < 0) {
            throw new RangeException('Last IP is less than first');
        }

        $this->lastIP = $ip;
    }

    public function getFirstIP(): IP
    {
        if (! $this->firstIP instanceof IP) {
            throw new RangeException('First IP is not set');
        }

        return $this->firstIP;
    }

    public function getLastIP(): IP
    {
        if (! $this->lastIP instanceof IP) {
            throw new RangeException('Last IP is not set');
        }

        return $this->lastIP;
    }

    /**
     * @return Network[]
     */
    public function getNetworks(): array
    {
        $span = $this->getSpanNetwork();
        $firstIP = $this->getFirstIP();
        $lastIP = $this->getLastIP();

        $networks = [];

        if ($span->getFirstIP()->inAddr() === $firstIP->inAddr()
            && $span->getLastIP()->inAddr() === $lastIP->inAddr()
        ) {
            $networks = [$span];
        } else {
            if ($span->getFirstIP()->inAddr() !== $firstIP->inAddr()) {
                $excluded = $span->exclude($firstIP->prev());
                foreach ($excluded as $network) {
                    if (strcmp($network->getFirstIP()->inAddr(), $firstIP->inAddr()) >= 0) {
                        $networks[] = $network;
                    }
                }
            }

            if ($span->getLastIP()->inAddr() !== $lastIP->inAddr()) {
                if ($networks === []) {
                    $excluded = $span->exclude($lastIP->next());
                } else {
                    $excluded = array_pop($networks);
                    $excluded = $excluded->exclude($lastIP->next());
                }

                foreach ($excluded as $network) {
                    $networks[] = $network;
                    if ($network->getLastIP()->inAddr() === $lastIP->inAddr()) {
                        break;
                    }
                }
            }

        }

        return $networks;
    }

    public function getSpanNetwork(): Network
    {
        $firstIP = $this->getFirstIP();
        $lastIP = $this->getLastIP();
        $xorIP = IP::parseInAddr($firstIP->inAddr() ^ $lastIP->inAddr());

        preg_match('/^(0*)/', $xorIP->toBin(), $match);

        $prefixLength = strlen($match[1]);

        $ip = IP::parseBin(str_pad(substr($firstIP->toBin(), 0, $prefixLength), $xorIP->getMaxPrefixLength(), '0'));

        return new Network($ip, Network::prefix2netmask($prefixLength, $ip->getVersion()));
    }

    public function current(): IP
    {
        if (! $this->currentIP instanceof IP) {
            $this->currentIP = $this->getFirstIP()->next($this->position);
        }

        return $this->currentIP;
    }

    public function key(): int
    {
        return $this->position;
    }

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
        return strcmp($this->current()->inAddr(), $this->getLastIP()->inAddr()) <= 0;
    }

    /**
     * Note: Countable requires int; very large IPv6 ranges may exceed PHP_INT_MAX.
     * For precise large-range sizes, compare getFirstIP()/getLastIP() or use BCMath externally.
     */
    public function count(): int
    {
        $lastLong = $this->getLastIP()->toLong();
        $firstLong = $this->getFirstIP()->toLong();

        return max(0, (int) bcadd(bcsub($lastLong, $firstLong), '1'));
    }
}
