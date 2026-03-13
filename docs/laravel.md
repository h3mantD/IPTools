# Laravel Integration

Back to index: [Documentation Index](README.md)

IPTools includes optional Laravel integration for database-backed range storage. The integration registers a service provider, publishes package assets, and binds the storage interface into Laravel's container.

## Installation

Install the package via Composer:

```bash
composer require h3mantd/iptools
```

If package discovery is disabled in your application, register `IPTools\IPToolsServiceProvider` manually.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=iptools-config
```

The published file contains the storage connection and table options:

```php
return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'ip_ranges'),
    ],
];
```

## Migrations

Publish the package migrations and run them:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

You may also run the installer command to publish all package assets and run migrations in one step:

```bash
php artisan iptools:install
```

## Using the Storage Interface

After installation, resolve `IPTools\Storage\RangeStorageInterface` from the container:

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);

$storage->store(Network::parse('10.24.0.0/16'), [
    'policy' => 'allow',
    'source' => 'admin-ui',
]);

$contains = $storage->contains(new IP('10.24.5.10')); // true

foreach ($storage->findContaining(new IP('10.24.5.10')) as $row) {
    $range = $row['range'];
    $metadata = $row['metadata'];
}
```

The `findContaining` method returns rows in the following shape:

```php
[
    'range' => IPTools\Range,
    'metadata' => array<string, mixed>,
]
```

## Publishing the Optional Model Stub

If you prefer querying the table with Eloquent directly, publish the model stub:

```bash
php artisan vendor:publish --tag=iptools-model
```

This publishes `app/Models/IpRange.php`, which you can customize for your application.

## Direct Adapter Usage

If you want explicit control over connection and table at runtime, instantiate the adapter directly:

```php
use Illuminate\Support\Facades\DB;
use IPTools\Storage\LaravelRangeStorage;

$storage = new LaravelRangeStorage(DB::connection(), 'ip_ranges');
```

## Troubleshooting

### Table is not available

Publish migrations and run them:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

### RangeStorageInterface does not resolve

Ensure package auto-discovery is enabled, or register `IPTools\IPToolsServiceProvider` manually.

### Incorrect database connection or table is used

Verify `IPTOOLS_DB_CONNECTION` and `IPTOOLS_RANGES_TABLE`, then clear cached configuration:

```bash
php artisan config:clear
```
