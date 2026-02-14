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
            $within = (strcmp($find->inAddr(), $firstIP->inAddr()) >= 0)
                && (strcmp($find->inAddr(), $lastIP->inAddr()) <= 0);

            return $within;
        }

        $within = (strcmp($find->getFirstIP()->inAddr(), $firstIP->inAddr()) >= 0)
            && (strcmp($find->getLastIP()->inAddr(), $lastIP->inAddr()) <= 0);

        return $within;
    }

    /**
     * @throws RangeException
     */
    public function setFirstIP(IP $ip): void
    {
        if ($this->lastIP !== null && strcmp($ip->inAddr(), $this->lastIP->inAddr()) > 0) {
            throw new RangeException('First IP is greater than second');
        }

        $this->firstIP = $ip;
    }

    /**
     * @throws RangeException
     */
    public function setLastIP(IP $ip): void
    {
        if ($this->firstIP !== null && strcmp($ip->inAddr(), $this->firstIP->inAddr()) < 0) {
            throw new RangeException('Last IP is less than first');
        }

        $this->lastIP = $ip;
    }

    public function getFirstIP(): IP
    {
        if ($this->firstIP === null) {
            throw new RangeException('First IP is not set');
        }

        return $this->firstIP;
    }

    public function getLastIP(): IP
    {
        if ($this->lastIP === null) {
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
        return $this->getFirstIP()->next($this->position);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return strcmp($this->getFirstIP()->next($this->position)->inAddr(), $this->getLastIP()->inAddr()) <= 0;
    }

    public function count(): int
    {
        $lastLong = $this->getLastIP()->toLong();
        $firstLong = $this->getFirstIP()->toLong();

        return max(0, (int) bcadd(bcsub($lastLong, $firstLong), '1'));
    }
}
