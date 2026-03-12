# Laravel Integration Guide

Back to index: [Documentation Index](README.md)

Laravel integration is optional and built on top of the core storage layer.

Primary classes:

- `IPTools\IPToolsServiceProvider`
- `IPTools\Storage\LaravelRangeStorage`
- `IPTools\Models\IpRange`

## Dependency Model

- Laravel applications already include Illuminate components via `laravel/framework`.
- Non-Laravel apps can ignore this section.
- If needed outside full Laravel, install:

```bash
composer require illuminate/support illuminate/database
```

## Service Provider

Provider responsibilities:

- merges `config/iptools.php`
- binds `RangeStorageInterface` to `LaravelRangeStorage`
- publishes package assets

Auto-discovery is configured in `composer.json`.

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

## Config

```php
return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'ip_ranges'),
    ],
];
```

## Usage via Container

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);
$storage->store(Network::parse('10.0.0.0/24'), ['policy' => 'allow']);

var_dump($storage->contains(new IP('10.0.0.42')));
```

## Direct Adapter Usage

```php
use IPTools\Storage\LaravelRangeStorage;

$storage = new LaravelRangeStorage(DB::connection(), 'ip_ranges');
```

## Model

`IPTools\Models\IpRange` (or published app model) provides Eloquent-level access to table rows.

```php
use App\Models\IpRange;

$rows = IpRange::query()->where('version', 4)->limit(20)->get();
```
