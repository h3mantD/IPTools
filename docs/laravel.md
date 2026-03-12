# Laravel Integration Guide

Back to index: [Documentation Index](README.md)

Laravel integration is optional and built on top of the core storage layer.

Primary classes:

- `IPTools\IPToolsServiceProvider`
- `IPTools\Storage\LaravelRangeStorage`
- `IPTools\Models\IpRange`

## Quick Start

1. Install the package.
2. Publish config and migration.
3. Run migrations.
4. Resolve `RangeStorageInterface` from the container.
5. Start storing and querying ranges.

## Dependency Model

- Laravel applications already include Illuminate components via `laravel/framework`.
- Non-Laravel apps can ignore this section.
- If needed outside full Laravel, install:

```bash
composer require illuminate/support illuminate/database
```

In a standard Laravel app, you usually do not need to install these manually.

## Service Provider

Provider responsibilities:

- merges `config/iptools.php`
- binds `RangeStorageInterface` to `LaravelRangeStorage`
- publishes package assets

Auto-discovery is configured in `composer.json`.

If you disable package auto-discovery, register `IPTools\IPToolsServiceProvider` manually.

## Publishable Assets

- `iptools-config` -> `config/iptools.php`
- `iptools-migrations` -> migration stub for `ip_ranges`
- `iptools-model` -> `app/Models/IpRange.php`

Commands:

```bash
php artisan vendor:publish --tag=iptools-config
php artisan vendor:publish --tag=iptools-migrations
php artisan vendor:publish --tag=iptools-model
php artisan migrate
```

You can publish only what you need. For example, if you prefer your own model, skip `iptools-model`.

## Config

```php
return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'ip_ranges'),
    ],
];
```

Config keys:

- `iptools.storage.connection` - Laravel DB connection name (nullable)
- `iptools.storage.table` - table name used by storage adapters

## Usage via Container

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);
$storage->store(Network::parse('10.0.0.0/24'), ['policy' => 'allow']);

var_dump($storage->contains(new IP('10.0.0.42')));
```

This is the recommended approach for app-level code because it respects package config and container wiring.

## Direct Adapter Usage

```php
use IPTools\Storage\LaravelRangeStorage;

$storage = new LaravelRangeStorage(DB::connection(), 'ip_ranges');
```

Use this when you want explicit control over connection/table at call-site.

## Model

`IPTools\Models\IpRange` (or published app model) provides Eloquent-level access to table rows.

```php
use App\Models\IpRange;

$rows = IpRange::query()->where('version', 4)->limit(20)->get();
```

The model is optional and mainly useful for admin/reporting tools.

## Troubleshooting

### "Table is not available" runtime error

Cause: migration was not published or run.

Fix:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

### Nothing resolves for `RangeStorageInterface`

Cause: provider not loaded.

Fix: ensure package auto-discovery is enabled or register `IPTools\IPToolsServiceProvider` manually.

### Wrong database/table used

Cause: config mismatch.

Fix: verify `IPTOOLS_DB_CONNECTION` and `IPTOOLS_RANGES_TABLE`, then clear config cache:

```bash
php artisan config:clear
```
