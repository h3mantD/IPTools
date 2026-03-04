<?php

declare(strict_types=1);

namespace IPTools;

use Countable;
use IPTools\Exception\NetworkException;
use Iterator;
use Stringable;

/**
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 *
 * @implements Iterator<int, IP>
 */
class Network implements Countable, Iterator, Stringable
{
    use PropertyTrait;

    private ?IP $ip = null;

    private ?IP $netmask = null;

    private int $position = 0;

    private ?IP $currentIP = null;

    public function __construct(IP $ip, IP $netmask)
    {
        $this->setIP($ip);
        $this->setNetmask($netmask);
    }

    public function __toString(): string
    {
        return $this->getCIDR();
    }

    public static function parse(string|IP|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if ($data instanceof IP) {
            return new self($data, self::prefix2netmask($data->getMaxPrefixLength(), $data->getVersion()));
        }

        if (preg_match('~^(.+?)/(\d+)$~', $data, $matches)) {
            $ip = IP::parse($matches[1]);
            $netmask = self::prefix2netmask((int) $matches[2], $ip->getVersion());
        } elseif (str_contains($data, ' ')) {
            [$ip, $netmask] = explode(' ', $data, 2);
            $ip = IP::parse($ip);
            $netmask = IP::parse($netmask);
        } else {
            $ip = IP::parse($data);
            $netmask = self::prefix2netmask($ip->getMaxPrefixLength(), $ip->getVersion());
        }

        return new self($ip, $netmask);
    }

    /**
     * @throws NetworkException
     */
    public static function prefix2netmask(int|string $prefixLength, string $version): IP
    {
        if (! in_array($version, [IP::IP_V4, IP::IP_V6])) {
            throw new NetworkException('Wrong IP version');
        }

        $maxPrefixLength = $version === IP::IP_V4
            ? IP::IP_V4_MAX_PREFIX_LENGTH
            : IP::IP_V6_MAX_PREFIX_LENGTH;

        if (! is_numeric($prefixLength)
            || ! ($prefixLength >= 0 && $prefixLength <= $maxPrefixLength)
        ) {
            throw new NetworkException('Invalid prefix length');
        }

        $binIP = str_pad(str_pad('', (int) $prefixLength, '1'), $maxPrefixLength, '0');

        return IP::parseBin($binIP);
    }

    public static function netmask2prefix(IP $ip): int
    {
        return strlen(rtrim($ip->toBin(), '0'));
    }

    /**
     * @param  array<int, string|self>  $networks
     * @return Network[]
     */
    public static function summarize(array $networks): array
    {
        if ($networks === []) {
            return [];
        }

        $parsedNetworks = array_map(self::parse(...), $networks);

        usort($parsedNetworks, static function (self $first, self $second): int {
            $firstCompare = strcmp($first->getFirstIP()->inAddr(), $second->getFirstIP()->inAddr());
            if ($firstCompare !== 0) {
                return $firstCompare;
            }

            return $first->getPrefixLength() <=> $second->getPrefixLength();
        });

        $normalized = [];
        foreach ($parsedNetworks as $network) {
            $lastNormalized = $normalized[count($normalized) - 1] ?? null;

            if ($lastNormalized instanceof self
                && self::containsNetwork($lastNormalized, $network)
            ) {
                continue;
            }

            $normalized[] = $network;
        }

        do {
            $changed = false;
            $collapsed = [];

            foreach ($normalized as $network) {
                $lastCollapsed = $collapsed[count($collapsed) - 1] ?? null;
                if ($lastCollapsed instanceof self) {
                    $merged = self::tryMergeAdjacent($lastCollapsed, $network);
                    if ($merged instanceof self) {
                        $collapsed[count($collapsed) - 1] = $merged;
                        $changed = true;

                        continue;
                    }
                }

                $collapsed[] = $network;
            }

            $normalized = $collapsed;
        } while ($changed);

        return $normalized;
    }

    /**
     * @throws NetworkException
     */
    public function setIP(IP $ip): void
    {
        if ($this->netmask instanceof IP && $this->netmask->getVersion() !== $ip->getVersion()) {
            throw new NetworkException('IP version is not same as Netmask version');
        }

        $this->ip = $ip;
    }

    /**
     * @throws NetworkException
     */
    public function setNetmask(IP $ip): void
    {
        if (! preg_match('/^1*0*$/', $ip->toBin())) {
            throw new NetworkException('Invalid Netmask address format');
        }

        if ($this->ip instanceof IP && $ip->getVersion() !== $this->ip->getVersion()) {
            throw new NetworkException('Netmask version is not same as IP version');
        }

        $this->netmask = $ip;
    }

    public function setPrefixLength(int $prefixLength): void
    {
        $ip = $this->getIP();
        $this->setNetmask(self::prefix2netmask($prefixLength, $ip->getVersion()));
    }

    public function getIP(): IP
    {
        if (! $this->ip instanceof IP) {
            throw new NetworkException('IP address is not set');
        }

        return $this->ip;
    }

    public function getNetmask(): IP
    {
        if (! $this->netmask instanceof IP) {
            throw new NetworkException('Netmask is not set');
        }

        return $this->netmask;
    }

    public function getNetwork(): IP
    {
        $network = inet_ntop($this->getIP()->inAddr() & $this->getNetmask()->inAddr());
        if ($network === false) {
            throw new NetworkException('Unable to calculate network address');
        }

        return new IP($network);
    }

    public function getPrefixLength(): int
    {
        return self::netmask2prefix($this->getNetmask());
    }

    public function getCIDR(): string
    {
        return sprintf('%s/%s', $this->getNetwork(), $this->getPrefixLength());
    }

    public function getWildcard(): IP
    {
        $wildcard = inet_ntop(~$this->getNetmask()->inAddr());
        if ($wildcard === false) {
            throw new NetworkException('Unable to calculate wildcard address');
        }

        return new IP($wildcard);
    }

    public function getBroadcast(): IP
    {
        $broadcast = inet_ntop($this->getNetwork()->inAddr() | ~$this->getNetmask()->inAddr());
        if ($broadcast === false) {
            throw new NetworkException('Unable to calculate broadcast address');
        }

        return new IP($broadcast);
    }

    public function getFirstIP(): IP
    {
        return $this->getNetwork();
    }

    public function getLastIP(): IP
    {
        return $this->getBroadcast();
    }

    public function getBlockSize(): string|int
    {
        $ip = $this->getIP();
        $maxPrefixLength = $ip->getMaxPrefixLength();
        $prefixLength = $this->getPrefixLength();

        if ($ip->getVersion() === IP::IP_V6) {
            return bcpow('2', (string) ($maxPrefixLength - $prefixLength));
        }

        return 2 ** ($maxPrefixLength - $prefixLength);
    }

    public function getHosts(): Range
    {
        $firstHost = $this->getNetwork();
        $lastHost = $this->getBroadcast();
        $ip = $this->getIP();

        if ($ip->getVersion() === IP::IP_V4 && $this->getBlockSize() > 2) {
            $firstHost = IP::parseBin(substr($firstHost->toBin(), 0, $firstHost->getMaxPrefixLength() - 1).'1');
            $lastHost = IP::parseBin(substr($lastHost->toBin(), 0, $lastHost->getMaxPrefixLength() - 1).'0');
        }

        return new Range($firstHost, $lastHost);
    }

    /**
     * @return Network[]
     *
     * @throws NetworkException
     */
    public function exclude(string|IP|self $exclude): array
    {
        $exclude = self::parse($exclude);
        $ip = $this->getIP();

        if (strcmp($exclude->getFirstIP()->inAddr(), $this->getLastIP()->inAddr()) > 0
            || strcmp($exclude->getLastIP()->inAddr(), $this->getFirstIP()->inAddr()) < 0
        ) {
            throw new NetworkException('Exclude subnet not within target network');
        }

        $networks = [];

        $newPrefixLength = $this->getPrefixLength() + 1;
        if ($newPrefixLength > $ip->getMaxPrefixLength()) {
            return $networks;
        }

        $lower = clone $this;
        $lower->setPrefixLength($newPrefixLength);

        $upper = clone $lower;
        $upperFirstIP = $lower->getLastIP()->next();
        if (! $upperFirstIP instanceof IP) {
            return $networks;
        }

        $upper->setIP($upperFirstIP);

        while ($newPrefixLength <= $exclude->getPrefixLength()) {
            $range = new Range($lower->getFirstIP(), $lower->getLastIP());
            if ($range->contains($exclude)) {
                $matched = $lower;
                $unmatched = $upper;
            } else {
                $matched = $upper;
                $unmatched = $lower;
            }

            $networks[] = clone $unmatched;

            if (++$newPrefixLength > $this->getNetwork()->getMaxPrefixLength()) {
                break;
            }

            $matched->setPrefixLength($newPrefixLength);
            $unmatched->setPrefixLength($newPrefixLength);
            $nextIP = $matched->getLastIP()->next();
            if (! $nextIP instanceof IP) {
                break;
            }

            $unmatched->setIP($nextIP);
        }

        usort($networks, static fn (self $a, self $b): int => strcmp($a->getFirstIP()->inAddr(), $b->getFirstIP()->inAddr()));

        return $networks;
    }

    /**
     * @return Network[]
     *
     * @throws NetworkException
     */
    public function moveTo(int|string $prefixLength): array
    {
        if (! is_int($prefixLength) && ! preg_match('/^\d+$/', $prefixLength)) {
            throw new NetworkException('Invalid prefix length');
        }

        $prefixLength = (int) $prefixLength;
        $ip = $this->getIP();
        $maxPrefixLength = $ip->getMaxPrefixLength();

        if ($prefixLength <= $this->getPrefixLength() || $prefixLength > $maxPrefixLength) {
            throw new NetworkException('Invalid prefix length');
        }

        $netmask = self::prefix2netmask($prefixLength, $ip->getVersion());
        $networks = [];

        $subnet = clone $this;
        $subnet->setPrefixLength($prefixLength);
        $targetLastInAddr = $this->getLastIP()->inAddr();

        while (strcmp($subnet->getFirstIP()->inAddr(), $targetLastInAddr) <= 0) {
            $networks[] = $subnet;

            $nextIP = $subnet->getLastIP()->next();
            if (! $nextIP instanceof IP) {
                break;
            }

            $subnet = new self($nextIP, $netmask);
        }

        return $networks;
    }

    public function current(): IP
    {
        if (! $this->currentIP instanceof IP) {
            $ip = $this->getFirstIP()->next($this->position);
            if (! $ip instanceof IP) {
                throw new NetworkException('Iterator position is out of range');
            }

            $this->currentIP = $ip;
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
            $this->currentIP = $this->currentIP->next(); // ?IP; null signals boundary
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

    public function getCountPrecise(): string
    {
        return (string) $this->getBlockSize();
    }

    /**
     * Note: Countable requires int; very large IPv6 blocks may exceed PHP_INT_MAX.
     * Use getCountPrecise() for precise big-integer size information.
     */
    public function count(): int
    {
        $count = $this->getBlockSize();

        if (is_int($count)) {
            return max(0, $count);
        }

        $maxIntString = (string) PHP_INT_MAX;
        if (strlen($count) > strlen($maxIntString)
            || (strlen($count) === strlen($maxIntString) && strcmp($count, $maxIntString) > 0)
        ) {
            return PHP_INT_MAX;
        }

        return max(0, (int) $count);
    }

    private static function containsNetwork(self $container, self $contained): bool
    {
        if ($container->getIP()->getVersion() !== $contained->getIP()->getVersion()) {
            return false;
        }

        return strcmp($contained->getFirstIP()->inAddr(), $container->getFirstIP()->inAddr()) >= 0
            && strcmp($contained->getLastIP()->inAddr(), $container->getLastIP()->inAddr()) <= 0;
    }

    private static function tryMergeAdjacent(self $left, self $right): ?self
    {
        if ($left->getIP()->getVersion() !== $right->getIP()->getVersion()) {
            return null;
        }

        if ($left->getPrefixLength() !== $right->getPrefixLength()) {
            return null;
        }

        $prefixLength = $left->getPrefixLength();
        if ($prefixLength === 0) {
            return null;
        }

        $nextAfterLeft = $left->getLastIP()->next();
        if (! $nextAfterLeft instanceof IP) {
            return null;
        }

        if (strcmp($nextAfterLeft->inAddr(), $right->getFirstIP()->inAddr()) !== 0) {
            return null;
        }

        $supernetPrefix = $prefixLength - 1;
        $supernet = new self($left->getFirstIP(), self::prefix2netmask($supernetPrefix, $left->getIP()->getVersion()));

        if (strcmp($supernet->getFirstIP()->inAddr(), $left->getFirstIP()->inAddr()) !== 0
            || strcmp($supernet->getLastIP()->inAddr(), $right->getLastIP()->inAddr()) !== 0
        ) {
            return null;
        }

        return $supernet;
    }
}
