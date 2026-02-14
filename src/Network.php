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
final class Network implements Countable, Iterator, Stringable
{
    use PropertyTrait;

    private ?IP $ip = null;

    private ?IP $netmask = null;

    private int $position = 0;

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

        $binIP = mb_str_pad(mb_str_pad('', (int) $prefixLength, '1'), $maxPrefixLength, '0');

        return IP::parseBin($binIP);
    }

    public static function netmask2prefix(IP $ip): int
    {
        return mb_strlen(mb_rtrim($ip->toBin(), '0'));
    }

    /**
     * @throws NetworkException
     */
    public function setIP(IP $ip): void
    {
        if ($this->netmask !== null && $this->netmask->getVersion() !== $ip->getVersion()) {
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

        if ($this->ip !== null && $ip->getVersion() !== $this->ip->getVersion()) {
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
        if ($this->ip === null) {
            throw new NetworkException('IP address is not set');
        }

        return $this->ip;
    }

    public function getNetmask(): IP
    {
        if ($this->netmask === null) {
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
            $firstHost = IP::parseBin(mb_substr($firstHost->toBin(), 0, $firstHost->getMaxPrefixLength() - 1).'1');
            $lastHost = IP::parseBin(mb_substr($lastHost->toBin(), 0, $lastHost->getMaxPrefixLength() - 1).'0');
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
        $upper->setIP($lower->getLastIP()->next());

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
            $unmatched->setIP($matched->getLastIP()->next());
        }

        sort($networks);

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
            throw new NetworkException('Invalid prefix length ');
        }

        $prefixLength = (int) $prefixLength;
        $ip = $this->getIP();
        $maxPrefixLength = $ip->getMaxPrefixLength();

        if ($prefixLength <= $this->getPrefixLength() || $prefixLength > $maxPrefixLength) {
            throw new NetworkException('Invalid prefix length ');
        }

        $netmask = self::prefix2netmask($prefixLength, $ip->getVersion());
        $networks = [];

        $subnet = clone $this;
        $subnet->setPrefixLength($prefixLength);

        while ($subnet->getIP()->inAddr() <= $this->getLastIP()->inAddr()) {
            $networks[] = $subnet;
            $subnet = new self($subnet->getLastIP()->next(), $netmask);
        }

        return $networks;
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
        return max(0, (int) $this->getBlockSize());
    }
}
