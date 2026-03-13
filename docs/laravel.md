# Laravel Integration Guide

Back to index: [Documentation Index](README.md)

Laravel integration is optional and built on top of the core storage layer.

Primary classes:

- `IPTools\IPToolsServiceProvider`
- `IPTools\Storage\LaravelRangeStorage`
- `IPTools\Models\IpRange`
- `IPTools\Console\InstallCommand` (`iptools:install`)

## Quick Start

1. Install the package.
2. Run `php artisan iptools:install`.
3. Resolve `RangeStorageInterface` from the container.
4. Start storing and querying ranges.

Or run commands manually if you want granular control.

## Dependency Model

- Laravel applications already include Illuminate components via `laravel/framework`.
- Non-Laravel apps can ignore this section.
- If needed outside full Laravel, install:

```bash
composer require illuminate/support illuminate/database illuminate/console
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
- `iptools-migrations` -> package migration files
- `iptools-model` -> `app/Models/IpRange.php`

Commands:

```bash
php artisan iptools:install

# Manual alternative
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

## End-to-End Example

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);

$network = Network::parse('10.24.0.0/16');

$storage->store($network, [
    'policy' => 'allow',
    'source' => 'admin-ui',
    'priority' => 100,
]);

$contains = $storage->contains(new IP('10.24.5.10')); // true

$matches = iterator_to_array($storage->findContaining(new IP('10.24.5.10')), false);
foreach ($matches as $row) {
    $range = $row['range'];      // IPTools\Range
    $metadata = $row['metadata']; // array<string, mixed>
}

$storage->delete($network);
```

`findContaining()` always returns `Range` rows with metadata payloads.

## Direct Adapter Usage

```php
use Illuminate\Support\Facades\DB;
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

## Custom Connection and Table

Set config values:

```php
return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION', 'mysql'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'tenant_ip_ranges'),
    ],
];
```

Then clear cached config:

```bash
php artisan config:clear
```

## Troubleshooting

### "Table is not available" runtime error

Cause: migration was not published or run.

Fix:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

Or run the one-step installer:

```bash
php artisan iptools:install
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
