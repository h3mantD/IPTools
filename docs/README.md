# IPTools Documentation

Welcome to the IPTools docs. This section explains concepts first, then APIs, then integration patterns.

If you prefer to jump straight to method signatures, start with [API Reference](api-reference.md).

## Documentation Map

- [IP Guide](ip.md)
  - Parse and format addresses
  - Address type classification
  - Address arithmetic and overflow behavior
  - IPv4/IPv6 transition helpers
- [Parser Guide](parser.md)
  - Flexible parser entry points
  - Parse flags and strict mode
  - Ports, zone IDs, non-decimal inputs, and wildcards
- [Network Guide](network.md)
  - CIDR modeling and subnet math
  - Host helpers and boundary behavior
  - Exclusion, splitting, summarization, and iteration
- [Range Guide](range.md)
  - Inclusive interval modeling
  - Range decomposition into minimal CIDRs
  - Offset addressing and precise counts
- [RangeSet Guide](rangeset.md)
  - Canonical range normalization
  - Set algebra (`union`, `intersect`, `subtract`)
  - Overlap and containment queries
- [Storage Guide](storage.md)
  - Database-backed lookup model
  - 16-byte address encoding strategy
  - SQL schema and query usage
- [Laravel Integration Guide](laravel.md)
  - Service provider wiring
  - Publishable config/migration/model stubs
  - Container and adapter usage
- [API Reference](api-reference.md)
  - Public classes and methods in one place

## Suggested Learning Path

1. [IP Guide](ip.md) to understand the core value object
2. [Network Guide](network.md) and [Range Guide](range.md) for block/range operations
3. [Parser Guide](parser.md) for input handling in real systems
4. [RangeSet Guide](rangeset.md) for policy and set operations
5. [Storage Guide](storage.md) for persistence and lookups
6. [Laravel Integration Guide](laravel.md) if your app uses Laravel
7. [API Reference](api-reference.md) as an ongoing lookup table

## Conventions Used in These Docs

- `IP` means `IPTools\IP`
- `Network` means `IPTools\Network`
- `Range` means `IPTools\Range`
- Numeric values that may exceed native integer size are returned as `numeric-string`
- `count()` methods return `int` and may clamp at `PHP_INT_MAX`

## Practical Notes

- IPv4 and IPv6 are supported consistently across all core types.
- Big-number arithmetic relies on `ext-bcmath`.
- For large IPv6 spaces, favor lazy/decomposed operations over full address iteration.
