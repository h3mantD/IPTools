# Laravel Integration

- [Documentation](README.md)

IPTools includes optional Laravel integration that wires the database storage layer into Laravel's service container, migrations, and Artisan. If you don't use Laravel, see the [Database Storage](database-storage.md) guide for standalone PDO usage.

## Installation

```bash
composer require h3mantd/iptools
```

> **Note:** The service provider is registered automatically via package discovery. If you've disabled auto-discovery, add `IPTools\IPToolsServiceProvider::class` to your `providers` array in `config/app.php`.

## Quick Setup

The fastest way to get everything set up:

```bash
php artisan iptools:install
```

This single command publishes the config file, migration, optional Eloquent model, and runs the migration. You're ready to go.

> **Tip:** Use `--force` to overwrite previously published files, or `--no-migrate` if you want to review the migration before running it.

## Step-By-Step Setup

If you prefer to control each step:

### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=iptools-config
```

This creates `config/iptools.php`:

```php
return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'ip_ranges'),
    ],
];
```

Set `IPTOOLS_DB_CONNECTION` in your `.env` to use a specific database connection. Leave it unset to use Laravel's default connection.

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

This creates the `ip_ranges` table with the correct schema for your database driver.

### 3. Publish the Model (Optional)

```bash
php artisan vendor:publish --tag=iptools-model
```

This creates `app/Models/IpRange.php` — a customizable Eloquent model for the `ip_ranges` table. You only need this if you want to query the table with Eloquent directly.

## Using the Storage Interface

After setup, resolve `RangeStorageInterface` from the container:

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);

// Store a range with metadata
$storage->store(Network::parse('10.0.0.0/24'), [
    'policy' => 'allow',
    'source' => 'admin-ui',
]);

// Check if an IP is in any stored range
$storage->contains(new IP('10.0.0.42')); // true

// Find all ranges containing an IP
foreach ($storage->findContaining(new IP('10.0.0.42')) as $match) {
    echo $match['metadata']['policy']; // 'allow'
}

// Delete a range
$storage->delete(Network::parse('10.0.0.0/24'));

// Count stored ranges
echo $storage->count();
```

You can also type-hint the interface in your controllers and services for dependency injection:

```php
use IPTools\Storage\RangeStorageInterface;

class FirewallController extends Controller
{
    public function check(Request $request, RangeStorageInterface $storage)
    {
        $clientIP = new IP($request->ip());

        if (! $storage->contains($clientIP)) {
            abort(403, 'IP not in allowed ranges');
        }

        // proceed
    }
}
```

## Practical Example: IP-Based Middleware

```php
namespace App\Http\Middleware;

use Closure;
use IPTools\IP;
use IPTools\Storage\RangeStorageInterface;

class AllowListedIPs
{
    public function __construct(
        private readonly RangeStorageInterface $storage,
    ) {}

    public function handle($request, Closure $next)
    {
        $clientIP = new IP($request->ip());

        if (! $this->storage->contains($clientIP)) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
```

## Direct Adapter Usage

If you need explicit control over the connection or table name at runtime:

```php
use Illuminate\Support\Facades\DB;
use IPTools\Storage\LaravelRangeStorage;

$storage = new LaravelRangeStorage(DB::connection('mysql'), 'custom_ip_ranges');
```

The `LaravelRangeStorage` adapter bridges Laravel's `Connection` to the underlying `SqlRangeStorage`. It handles PDO reconnects transparently.

## Troubleshooting

### "Table 'ip_ranges' is not available"

The migration hasn't been run. Publish and migrate:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

Or use the installer: `php artisan iptools:install`

### RangeStorageInterface Does Not Resolve

The service provider isn't registered. Check that:

1. Package auto-discovery is enabled in your `composer.json` (the default)
2. Or `IPTools\IPToolsServiceProvider::class` is in your `providers` array

Then clear the bootstrap cache:

```bash
php artisan clear-compiled
```

### Wrong Database Connection

If ranges are being stored in the wrong database, set the `IPTOOLS_DB_CONNECTION` environment variable in your `.env`:

```env
IPTOOLS_DB_CONNECTION=mysql_secondary
```

> **Warning:** After changing environment variables, clear the config cache:
> ```bash
> php artisan config:clear
> ```

### Table Name Mismatch

If you customized the table name, make sure both the migration and config use the same name:

```env
IPTOOLS_RANGES_TABLE=my_ip_ranges
```

## What's Next?

- **[Database Storage](database-storage.md)** — Standalone PDO usage, schema details, and encoding internals
- **[Getting Started](getting-started.md)** — Core library features without Laravel
- **[API Reference](api-reference.md)** — Full method listing for storage classes
