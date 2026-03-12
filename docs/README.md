# IPTools Documentation

This directory contains detailed documentation for all major IPTools features and APIs.

## Contents

- [IP Guide](ip.md) - IP parsing, conversion, classification, arithmetic, and overflow behavior
- [Parser Guide](parser.md) - Flexible parser, parse flags, and parsed result object
- [Network Guide](network.md) - Network math, subnet operations, host helpers, summarization, and iteration
- [Range Guide](range.md) - Range parsing, containment, indexing, decomposition, and counting
- [RangeSet Guide](rangeset.md) - RangeSet normalization and set algebra (`union`, `intersect`, `subtract`)
- [Storage Guide](storage.md) - SQL-backed range storage, schema, metadata, and query patterns
- [Laravel Integration Guide](laravel.md) - Laravel service provider, publishable assets, model, and adapter usage
- [API Reference](api-reference.md) - API-focused reference across public classes

## Suggested Reading Order

1. [IP Guide](ip.md)
2. [Network Guide](network.md)
3. [Range Guide](range.md)
4. [Parser Guide](parser.md)
5. [RangeSet Guide](rangeset.md)
6. [Storage Guide](storage.md)
7. [Laravel Integration Guide](laravel.md) (only if you use Laravel)
8. [API Reference](api-reference.md) for method-by-method lookup

## Notes

- The library supports IPv4 and IPv6 throughout.
- Large numeric operations are handled with `ext-bcmath` where needed.
- For very large ranges/networks, use precise counters (`getCountPrecise`) instead of relying only on `count()`.
