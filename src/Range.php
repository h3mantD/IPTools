<?php

declare(strict_types=1);

namespace IPTools;

use Countable;
use Generator;
use InvalidArgumentException;
use IPTools\Exception\RangeException;
use Iterator;
use OutOfBoundsException;

/**
 * Inclusive IP interval from a first address to a last address.
 *
 * Supports iteration over every address in the range, decomposition into
 * minimal CIDR networks, offset-based addressing (including negative offsets
 * from the end), and containment checks.
 *
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 *
 * @implements Iterator<int, IP>
 */
class Range implements Countable, Iterator
{
    use IPIteratorTrait;
    use PropertyTrait;

    private ?IP $firstIP = null;

    private ?IP $lastIP = null;

    /**
     * @throws RangeException
     */
    public function __construct(IP $firstIP, IP $lastIP)
    {
        $this->setFirstIP($firstIP);
        $this->setLastIP($lastIP);
    }

    /**
     * Parse from CIDR, wildcard, dash-separated, or single IP notation.
     *
     * Dispatch: CIDR/netmask → Network, wildcard → 0/255 substitution,
     * dash → explicit endpoints, bare IP → single-address range.
     */
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
            // Single IP → range of one address
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
        return iterator_to_array($this->iterateNetworks(), false);
    }

    /**
     * Decompose this range into the minimum set of CIDR networks (lazy).
     *
     * Greedy algorithm: at each position, pick the largest network that
     * fits within the remaining range, then advance past it.
     *
     * @return Generator<int, Network>
     */
    public function iterateNetworks(): Generator
    {
        $firstIP = $this->getFirstIP();
        $lastIP = $this->getLastIP();
        $currentIP = $firstIP;

        while (strcmp($currentIP->inAddr(), $lastIP->inAddr()) <= 0) {
            $network = $this->getLargestNetworkFrom($currentIP, $lastIP);
            yield $network;

            $nextIP = $network->getLastIP()->next();
            if (! $nextIP instanceof IP) {
                break;
            }

            $currentIP = $nextIP;
        }
    }

    public function getFirstNetwork(): Network
    {
        return $this->getLargestNetworkFrom($this->getFirstIP(), $this->getLastIP());
    }

    public function getLastNetwork(): Network
    {
        $lastNetwork = null;
        foreach ($this->iterateNetworks() as $network) {
            $lastNetwork = $network;
        }

        if (! $lastNetwork instanceof Network) {
            throw new RangeException('Unable to determine last network');
        }

        return $lastNetwork;
    }

    public function getNthNetwork(int $index): ?Network
    {
        if ($index < 0) {
            throw new RangeException('Network index must be non-negative');
        }

        foreach ($this->iterateNetworks() as $position => $network) {
            if ($position === $index) {
                return $network;
            }
        }

        return null;
    }

    /**
     * Smallest single CIDR network that fully contains this range.
     *
     * XOR the first and last addresses: matching leading bits (zeros in XOR)
     * are the common prefix; the prefix length is the count of those leading zeros.
     * The network address is the first IP's bits up to that prefix, zero-padded.
     */
    public function getSpanNetwork(): Network
    {
        $firstIP = $this->getFirstIP();
        $lastIP = $this->getLastIP();
        $xorIP = IP::parseInAddr($firstIP->inAddr() ^ $lastIP->inAddr());

        // Count leading zeros in the XOR → number of shared prefix bits
        preg_match('/^(0*)/', $xorIP->toBin(), $match);

        $prefixLength = strlen($match[1]);

        $ip = IP::parseBin(str_pad(substr($firstIP->toBin(), 0, $prefixLength), $xorIP->getMaxPrefixLength(), '0'));

        return new Network($ip, Network::prefix2netmask($prefixLength, $ip->getVersion()));
    }

    /**
     * Address at a 0-based offset from the range start.
     * Negative offsets count from the end (-1 is the last address).
     * Returns null if offset is outside the range.
     */
    public function addressAt(int|string $offset): ?IP
    {
        $offset = (string) $offset;
        if (preg_match('/^-?\d+$/', $offset) !== 1) {
            throw new InvalidArgumentException('Offset must be an integer string');
        }

        /** @var numeric-string $offset */
        $firstLong = $this->getFirstIP()->toLong();
        $lastLong = $this->getLastIP()->toLong();
        $version = $this->getFirstIP()->getVersion();

        if (bccomp($offset, '0', 0) >= 0) {
            $result = bcadd($firstLong, $offset, 0);
            if (bccomp($result, $lastLong, 0) > 0) {
                return null;
            }
        } else {
            $result = bcadd(bcadd($lastLong, '1', 0), $offset, 0);
            if (bccomp($result, $firstLong, 0) < 0) {
                return null;
            }
        }

        return IP::parseLong($result, $version);
    }

    /**
     * Like addressAt() but throws \OutOfBoundsException when offset is outside the range.
     *
     * @throws OutOfBoundsException
     */
    public function addressAtOrFail(int|string $offset): IP
    {
        $ip = $this->addressAt($offset);
        if (! $ip instanceof IP) {
            throw new OutOfBoundsException(
                sprintf(
                    'Offset %s is outside the range [%s, %s]',
                    $offset,
                    $this->getFirstIP(),
                    $this->getLastIP()
                )
            );
        }

        return $ip;
    }

    /**
     * @return numeric-string
     */
    public function getCountPrecise(): string
    {
        return bcadd(bcsub($this->getLastIP()->toLong(), $this->getFirstIP()->toLong()), '1');
    }

    /**
     * Note: Countable requires int; very large IPv6 ranges may exceed PHP_INT_MAX.
     * Use getCountPrecise() for precise big-integer size information.
     */
    public function count(): int
    {
        $count = $this->getCountPrecise();

        if (bccomp($count, (string) PHP_INT_MAX) === 1) {
            return PHP_INT_MAX;
        }

        return max(0, (int) $count);
    }

    /**
     * Find the largest valid CIDR network starting at $firstIP that fits within [$firstIP, $lastIP].
     *
     * A valid CIDR block at a given address requires that address's trailing
     * zeros can accommodate the host bits. For example, 10.0.0.8 (binary ...1000)
     * has 3 trailing zeros, so the largest block is /29 (3 host bits).
     *
     * We try the maximum host bits (trailing zeros) first and shrink until the
     * resulting broadcast doesn't exceed $lastIP.
     */
    private function getLargestNetworkFrom(IP $firstIP, IP $lastIP): Network
    {
        $maxPrefixLength = $firstIP->getMaxPrefixLength();
        $firstBinary = $firstIP->toBin();
        $lastBinary = $lastIP->toBin();

        // Count trailing zeros in firstIP — they limit how large a CIDR block can start here
        $trailingZeros = 0;
        for ($position = $maxPrefixLength - 1; $position >= 0; $position--) {
            if ($firstBinary[$position] !== '0') {
                break;
            }

            $trailingZeros++;
        }

        // Try the largest candidate (most host bits) first; shrink until it fits within lastIP
        $hostBits = 0;
        for ($candidateHostBits = $trailingZeros; $candidateHostBits >= 0; $candidateHostBits--) {
            $prefixLength = $maxPrefixLength - $candidateHostBits;
            $candidateLastBinary = substr($firstBinary, 0, $prefixLength).str_repeat('1', $candidateHostBits);

            if (strcmp($candidateLastBinary, $lastBinary) <= 0) {
                $hostBits = $candidateHostBits;
                break;
            }
        }

        $prefixLength = $maxPrefixLength - $hostBits;

        return new Network($firstIP, Network::prefix2netmask($prefixLength, $firstIP->getVersion()));
    }
}
