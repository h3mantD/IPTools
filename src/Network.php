<?php

declare(strict_types=1);

namespace IPTools;

use Countable;
use IPTools\Enums\IPVersion;
use IPTools\Exception\NetworkException;
use Iterator;
use Stringable;

/**
 * Represents a CIDR network (an IP address paired with a netmask).
 *
 * The netmask determines the network/host boundary. Internally, all
 * operations use binary string comparison on the packed inet_pton form,
 * which handles both IPv4 and IPv6 uniformly.
 *
 * Implements Iterator to enumerate every IP in the network, and Countable
 * for the total address count. Use getCountPrecise() for large IPv6 blocks
 * that exceed PHP_INT_MAX.
 *
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 *
 * @implements Iterator<int, IP>
 */
class Network implements Countable, Iterator, Stringable
{
    use IPIteratorTrait;
    use PropertyTrait;

    private ?IP $ip = null;

    private ?IP $netmask = null;

    public function __construct(IP $ip, IP $netmask)
    {
        $this->setIP($ip);
        $this->setNetmask($netmask);
    }

    public function __toString(): string
    {
        return $this->getCIDR();
    }

    /**
     * Parse from CIDR (`10.0.0.0/24`), dotted netmask (`10.0.0.0 255.255.255.0`),
     * or bare IP (treated as /32 or /128).
     */
    public static function parse(string|IP|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        // Bare IP → single-host network (/32 or /128)
        if ($data instanceof IP) {
            return new self($data, self::prefix2netmask($data->getMaxPrefixLength(), $data->getVersion()));
        }

        if (preg_match('~^(.+?)/(\d+)$~', $data, $matches)) {
            $ip = IP::parse($matches[1]);
            $netmask = self::prefix2netmask((int) $matches[2], $ip->getVersion());
        } elseif (str_contains($data, ' ')) {
            // Space-separated: "IP NETMASK" (dotted decimal netmask)
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
     * Build a netmask IP from a prefix length (e.g., 24 → 255.255.255.0).
     *
     * Constructs a binary string of `prefixLength` ones followed by zeros,
     * then parses it as an IP address.
     *
     * @throws NetworkException
     */
    public static function prefix2netmask(int|string $prefixLength, IPVersion|string $version): IP
    {
        $version = IPVersion::resolve($version);
        $maxPrefixLength = $version->maxPrefixLength();

        if (! is_numeric($prefixLength)
            || ! ($prefixLength >= 0 && $prefixLength <= $maxPrefixLength)
        ) {
            throw new NetworkException('Invalid prefix length');
        }

        $binIP = str_pad(str_pad('', (int) $prefixLength, '1'), $maxPrefixLength, '0');

        return IP::parseBin($binIP);
    }

    /**
     * Derive prefix length from a netmask (e.g., 255.255.255.0 → 24).
     *
     * Counts the leading '1' bits by stripping trailing '0's from the binary form.
     */
    public static function netmask2prefix(IP $ip): int
    {
        return strlen(rtrim($ip->toBin(), '0'));
    }

    /**
     * Merge an array of networks into the minimal equivalent CIDR set.
     *
     * Two-phase algorithm:
     *   1. **Normalize** — sort by start address, remove networks already
     *      contained within a preceding (wider) network.
     *   2. **Collapse** — repeatedly merge adjacent same-prefix siblings
     *      into their parent supernet until no more merges are possible.
     *
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

        // Phase 1: Remove networks fully contained within a preceding wider network
        $normalized = [];
        foreach ($parsedNetworks as $network) {
            $lastNormalized = $normalized[count($normalized) - 1] ?? null;

            if ($lastNormalized instanceof self
                && $lastNormalized->containsRange($network)
            ) {
                continue;
            }

            $normalized[] = $network;
        }

        // Phase 2: Merge adjacent sibling networks (e.g., /25 + /25 → /24)
        // Repeat until stable because one merge may enable further merges.
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

    public function networkAddress(): IP
    {
        return $this->getNetwork();
    }

    public function broadcastAddress(): IP
    {
        return $this->getBroadcast();
    }

    /**
     * First usable host address.
     *
     * IPv6 networks have no broadcast concept, so the network address itself is usable.
     * IPv4 /31 (point-to-point, RFC 3021) and /32 are also fully usable.
     * For standard IPv4 (/30 and wider), the first host is network + 1.
     */
    public function firstHost(): IP
    {
        $network = $this->getNetwork();

        if ($this->getIP()->getVersion() === IPVersion::IPv6) {
            return $network;
        }

        // /31 (point-to-point per RFC 3021) and /32 use all addresses
        if ($this->getPrefixLength() >= 31) {
            return $network;
        }

        return $network->next() ?? throw new NetworkException('Unable to calculate first host address');
    }

    /**
     * Last usable host address.
     *
     * For standard IPv4 networks (/30 and wider), the broadcast address is
     * excluded, so the last host is broadcast - 1.
     */
    public function lastHost(): IP
    {
        $broadcast = $this->getBroadcast();

        if ($this->getIP()->getVersion() === IPVersion::IPv6) {
            return $broadcast;
        }

        if ($this->getPrefixLength() >= 31) {
            return $broadcast;
        }

        return $broadcast->previous() ?? throw new NetworkException('Unable to calculate last host address');
    }

    /**
     * Returns usable address count.
     *
     * IPv4: blockSize - 2 (network + broadcast) for /30 and wider.
     *        /31 (RFC 3021) and /32 are fully usable.
     * IPv6: all addresses are usable (no broadcast concept).
     */
    public function usableHostCount(): string|int
    {
        $blockSize = $this->getBlockSize();
        if ($this->getIP()->getVersion() === IPVersion::IPv6) {
            return $blockSize;
        }

        if ($this->getPrefixLength() >= 31) {
            return $blockSize;
        }

        if (is_int($blockSize)) {
            return max(0, $blockSize - 2);
        }

        /** @var numeric-string $blockSize */

        return bcsub($blockSize, '2');
    }

    public function isPointToPoint(): bool
    {
        if ($this->getIP()->getVersion() === IPVersion::IPv4 && $this->getPrefixLength() === 31) {
            return true;
        }

        return $this->getIP()->getVersion() === IPVersion::IPv6 && $this->getPrefixLength() === 127;
    }

    public function containsIP(IP|string $ip): bool
    {
        $candidate = $ip instanceof IP ? $ip : IP::parse($ip);
        if ($candidate->getVersion() !== $this->getIP()->getVersion()) {
            return false;
        }

        return strcmp($candidate->inAddr(), $this->getFirstIP()->inAddr()) >= 0
            && strcmp($candidate->inAddr(), $this->getLastIP()->inAddr()) <= 0;
    }

    public function containsRange(Range|self|IP|string $range): bool
    {
        if ($range instanceof self) {
            $candidate = new Range($range->getFirstIP(), $range->getLastIP());
        } elseif ($range instanceof Range) {
            $candidate = $range;
        } elseif ($range instanceof IP) {
            $candidate = new Range($range, $range);
        } else {
            $candidate = Range::parse($range);
        }

        if ($candidate->getFirstIP()->getVersion() !== $this->getIP()->getVersion()) {
            return false;
        }

        return strcmp($candidate->getFirstIP()->inAddr(), $this->getFirstIP()->inAddr()) >= 0
            && strcmp($candidate->getLastIP()->inAddr(), $this->getLastIP()->inAddr()) <= 0;
    }

    public function nextSubnet(): ?self
    {
        $step = (string) $this->getBlockSize();
        /** @var numeric-string $step */
        $version = $this->getIP()->getVersion();
        $max = $version->maxLong();
        $nextLong = bcadd($this->getNetwork()->toLong(), $step);
        if (bccomp($nextLong, $max) > 0) {
            return null;
        }

        return new self(IP::parseLong($nextLong, $version), $this->getNetmask());
    }

    public function previousSubnet(): ?self
    {
        $step = (string) $this->getBlockSize();
        /** @var numeric-string $step */
        $version = $this->getIP()->getVersion();
        $prevLong = bcsub($this->getNetwork()->toLong(), $step);
        if (bccomp($prevLong, '0') < 0) {
            return null;
        }

        return new self(IP::parseLong($prevLong, $version), $this->getNetmask());
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

        if ($ip->getVersion() === IPVersion::IPv6) {
            return bcpow('2', (string) ($maxPrefixLength - $prefixLength));
        }

        return 2 ** ($maxPrefixLength - $prefixLength);
    }

    /**
     * Range of usable host addresses (excludes network + broadcast for standard IPv4).
     *
     * For IPv4 blocks > 2 addresses: sets the last bit of the first address to 1
     * (network + 1) and the last bit of the broadcast to 0 (broadcast - 1).
     */
    public function getHosts(): Range
    {
        $firstHost = $this->getNetwork();
        $lastHost = $this->getBroadcast();
        $ip = $this->getIP();

        if ($ip->getVersion() === IPVersion::IPv4 && $this->getBlockSize() > 2) {
            // Flip last bit: network address → first usable, broadcast → last usable
            $firstHost = IP::parseBin(substr($firstHost->toBin(), 0, $firstHost->getMaxPrefixLength() - 1).'1');
            $lastHost = IP::parseBin(substr($lastHost->toBin(), 0, $lastHost->getMaxPrefixLength() - 1).'0');
        }

        return new Range($firstHost, $lastHost);
    }

    /**
     * Remove a subnet from this network, returning the remaining fragments.
     *
     * Uses a binary halving strategy: split the network into two halves,
     * keep the half that doesn't contain the exclude target, then repeat
     * on the matching half until we reach the exclude's prefix length.
     *
     * Example: excluding 10.0.0.128/26 from 10.0.0.0/24 returns
     *          [10.0.0.0/25, 10.0.0.192/26].
     *
     * @return Network[]
     *
     * @throws NetworkException
     */
    public function exclude(string|IP|self $exclude): array
    {
        $exclude = self::parse($exclude);
        $ip = $this->getIP();

        if ($exclude->getIP()->getVersion() !== $ip->getVersion()
            || strcmp($exclude->getFirstIP()->inAddr(), $this->getFirstIP()->inAddr()) < 0
            || strcmp($exclude->getLastIP()->inAddr(), $this->getLastIP()->inAddr()) > 0
        ) {
            throw new NetworkException('Exclude subnet not within target network');
        }

        $networks = [];

        // Start by splitting the current network into two halves (prefix + 1)
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

        // At each level: keep the non-matching half, subdivide the matching half
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
     * Split this network into subnets of the given (longer) prefix length.
     *
     * Example: 192.168.0.0/22 → moveTo(24) → [/24, /24, /24, /24].
     *
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

    /**
     * Attempt to merge two adjacent sibling networks into their parent supernet.
     *
     * Returns the merged supernet if both networks have the same prefix length,
     * are truly adjacent (left's last + 1 == right's first), and together form
     * a valid supernet (prefix - 1). Returns null otherwise.
     */
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
