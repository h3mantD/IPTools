# Comparison Guide

- [Documentation](README.md)

IPTools has four core types for representing IP address spaces. Here's how to choose between them.

## Quick Reference

| Class | What It Represents | Best For |
|-------|-------------------|----------|
| `IP` | A single address | Parsing, classification, arithmetic |
| `Network` | A CIDR block (IP + prefix length) | Subnet math, routing rules, firewall ACLs |
| `Range` | An inclusive interval [first, last] | DHCP pools, arbitrary allocations, non-CIDR spans |
| `RangeSet` | A normalized collection of ranges | Merging rules from multiple sources, set algebra |

## Decision Flowchart

**Start here:** What are you working with?

- **A single IP address** → Use `IP`
- **A CIDR prefix like `10.0.0.0/24`** → Use `Network`
- **A start/end span like `10.0.0.1-10.0.0.100`** → Use `Range`
- **Multiple ranges that might overlap** → Use `RangeSet`
- **Not sure if it's an IP, range, or CIDR** → Use `Parser::any()` and branch on the return type

## When to Use Each

### IP

You have a single address and want to inspect, classify, or do arithmetic on it.

```php
$ip = new IP('10.0.0.1');
$ip->isPrivate();     // classification
$ip->next(5);         // arithmetic
$ip->toLong();        // conversion
```

### Network

You have a CIDR block and need subnet math — host boundaries, containment, splitting, or summarization.

```php
$net = Network::parse('192.168.0.0/24');
$net->containsIP('192.168.0.42');  // containment
$net->moveTo(26);                  // split into /26s
$net->exclude('192.168.0.128/25'); // remove a subnet
```

### Range

Your boundaries don't align to a CIDR prefix, or your data is naturally expressed as start/end addresses.

```php
$range = Range::parse('10.0.0.50-10.0.0.150');
$range->contains(new IP('10.0.0.100'));  // containment
$range->getNetworks();                   // decompose to CIDRs
$range->addressAt(-1);                   // last address
```

> **Tip:** `Range` can parse CIDR notation too (`Range::parse('10.0.0.0/24')`), so if you're not sure whether your input is CIDR or start-end, `Range` handles both.

### RangeSet

You have multiple ranges from different sources and need to merge, diff, or intersect them.

```php
$allow = new RangeSet(['10.0.0.0/8', '172.16.0.0/12']);
$deny = new RangeSet(['10.0.99.0/24']);
$effective = $allow->subtract($deny);
```

## Can They Convert Between Each Other?

Yes, freely:

| From | To | How |
|------|-----|-----|
| `Network` → `Range` | `new Range($net->getFirstIP(), $net->getLastIP())` |
| `Range` → `Network[]` | `$range->getNetworks()` (decompose to CIDRs) |
| `Range` → `Network` | `$range->getSpanNetwork()` (smallest covering CIDR) |
| `Range[]` → `RangeSet` | `new RangeSet($ranges)` |
| `RangeSet` → `Range[]` | `$set->getRanges()` |
| `RangeSet` → `Network[]` | `$set->toCidrs()` |

## Still Not Sure?

- If your code says `containsIP()`, you probably want `Network` or `Range`
- If your code says `union()` / `subtract()`, you want `RangeSet`
- If your code says `next()` / `previous()` / `isPrivate()`, you want `IP`
- If you're receiving user input, start with `Parser::any()` and see what comes back
