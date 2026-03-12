# IPTools Documentation

This directory contains detailed documentation for all major IPTools features and APIs.

## Contents

- `docs/ip.md` - IP parsing, conversion, classification, arithmetic, and overflow behavior
- `docs/parser.md` - Flexible parser, parse flags, and parsed result object
- `docs/network.md` - Network math, subnet operations, host helpers, summarization, and iteration
- `docs/range.md` - Range parsing, containment, indexing, decomposition, and counting
- `docs/rangeset.md` - RangeSet normalization and set algebra (`union`, `intersect`, `subtract`)
- `docs/storage.md` - SQL-backed range storage, schema, metadata, and query patterns
- `docs/laravel.md` - Laravel service provider, publishable assets, model, and adapter usage
- `docs/api-reference.md` - API-focused reference across public classes

## Suggested Reading Order

1. `docs/ip.md`
2. `docs/network.md`
3. `docs/range.md`
4. `docs/parser.md`
5. `docs/rangeset.md`
6. `docs/storage.md`
7. `docs/laravel.md` (only if you use Laravel)
8. `docs/api-reference.md` for method-by-method lookup

## Notes

- The library supports IPv4 and IPv6 throughout.
- Large numeric operations are handled with `ext-bcmath` where needed.
- For very large ranges/networks, use precise counters (`getCountPrecise`) instead of relying only on `count()`.
