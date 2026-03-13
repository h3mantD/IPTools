<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Facade;
use IPTools\IP;
use IPTools\IPToolsServiceProvider;
use IPTools\Network;
use IPTools\Storage\LaravelRangeStorage;
use IPTools\Storage\RangeStorageInterface;
use PHPUnit\Framework\TestCase;

final class LaravelIntegrationDxTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setFacadeApplication(null);
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_service_provider_registers_bindings_and_boot_publishes_assets(): void
    {
        $config = new FakeConfig([
            'iptools' => [
                'storage' => [
                    'connection' => 'sqlite',
                    'table' => 'custom_ip_ranges',
                ],
            ],
            'database' => [
                'migrations' => [
                    'update_date_on_publish' => false,
                ],
            ],
        ]);

        $pdo = new PDO('sqlite::memory:');
        $connection = $this->createMock(Connection::class);
        $connection->method('getPdo')->willReturn($pdo);

        $database = $this->createMock(DatabaseManager::class);
        $database->expects($this->once())
            ->method('connection')
            ->with('sqlite')
            ->willReturn($connection);

        $laravelApp = $this->createMock(Application::class);
        $laravelApp->method('make')->willReturnMap([
            ['config', $config],
            ['db', $database],
        ]);

        $fakeApp = new FakeProviderApp($config, $laravelApp, true);
        Facade::setFacadeApplication($fakeApp);

        $provider = new IPToolsServiceProvider($fakeApp);
        $provider->register();
        $provider->boot();

        $storage = $fakeApp->make(LaravelRangeStorage::class);
        $this->assertInstanceOf(LaravelRangeStorage::class, $storage);
        $this->assertSame(LaravelRangeStorage::class, $fakeApp->aliases[RangeStorageInterface::class]);

        $pdo->exec('CREATE TABLE custom_ip_ranges (id INTEGER PRIMARY KEY AUTOINCREMENT, version INTEGER NOT NULL, start_bin BLOB NOT NULL, end_bin BLOB NOT NULL, metadata TEXT NULL)');
        $pdo->exec('CREATE INDEX custom_ip_ranges_lookup_idx ON custom_ip_ranges (version, start_bin, end_bin)');
        $storage->store(Network::parse('10.10.0.0/16'), ['scope' => 'provider']);
        $this->assertTrue($storage->contains(new IP('10.10.1.2')));

        $configPaths = IPToolsServiceProvider::pathsToPublish(IPToolsServiceProvider::class, 'iptools-config');
        $migrationPaths = IPToolsServiceProvider::pathsToPublish(IPToolsServiceProvider::class, 'iptools-migrations');
        $modelPaths = IPToolsServiceProvider::pathsToPublish(IPToolsServiceProvider::class, 'iptools-model');

        $this->assertNotEmpty($configPaths);
        $this->assertNotEmpty($migrationPaths);
        $this->assertNotEmpty($modelPaths);

        $configSources = array_keys($configPaths);
        $this->assertNotEmpty(
            array_filter(
                $configSources,
                static fn (string $source): bool => str_ends_with(str_replace('\\', '/', $source), '/config/iptools.php')
            )
        );

        $modelSources = array_keys($modelPaths);
        $this->assertNotEmpty(
            array_filter(
                $modelSources,
                static fn (string $source): bool => str_ends_with(str_replace('\\', '/', $source), '/stubs/IpRange.php.stub')
            )
        );

        $migrationSources = array_keys($migrationPaths);
        $this->assertNotEmpty(
            array_filter(
                $migrationSources,
                static fn (string $source): bool => str_ends_with(str_replace('\\', '/', $source), '/database/migrations')
            )
        );

    }

    public function test_laravel_range_storage_handles_pdo_refresh(): void
    {
        $pdoA = new PDO('sqlite::memory:');
        $pdoA->exec('CREATE TABLE ip_ranges (id INTEGER PRIMARY KEY AUTOINCREMENT, version INTEGER NOT NULL, start_bin BLOB NOT NULL, end_bin BLOB NOT NULL, metadata TEXT NULL)');
        $pdoA->exec('CREATE INDEX ip_ranges_lookup_idx ON ip_ranges (version, start_bin, end_bin)');

        $pdoB = new PDO('sqlite::memory:');
        $pdoB->exec('CREATE TABLE ip_ranges (id INTEGER PRIMARY KEY AUTOINCREMENT, version INTEGER NOT NULL, start_bin BLOB NOT NULL, end_bin BLOB NOT NULL, metadata TEXT NULL)');
        $pdoB->exec('CREATE INDEX ip_ranges_lookup_idx ON ip_ranges (version, start_bin, end_bin)');

        $activePdo = $pdoA;

        $connection = $this->createMock(Connection::class);
        $connection->method('getPdo')->willReturnCallback(static function () use (&$activePdo): PDO {
            return $activePdo;
        });

        $storage = new LaravelRangeStorage($connection, 'ip_ranges');
        $storage->store(Network::parse('10.0.0.0/24'), ['source' => 'a']);
        $this->assertTrue($storage->contains(new IP('10.0.0.42')));

        $activePdo = $pdoB;

        $rows = iterator_to_array($storage->findContaining(new IP('10.0.0.42')));
        $this->assertSame([], $rows);

        $storage->store(Network::parse('10.0.0.0/24'), ['source' => 'b']);
        $this->assertTrue($storage->contains(new IP('10.0.0.42')));
        $this->assertSame(1, $storage->count());

        $storage->delete(Network::parse('10.0.0.0/24'));
        $this->assertFalse($storage->contains(new IP('10.0.0.42')));
    }
}

final class FakeProviderApp implements ArrayAccess, CachesConfiguration
{
    /** @var array<string, mixed> */
    public array $bindings = [];

    /** @var array<string, string> */
    public array $aliases = [];

    public FakeArtisan $artisan;

    public function __construct(
        public FakeConfig $config,
        private readonly Application $laravelApp,
        private readonly bool $runningInConsole,
    ) {
        $this->artisan = new FakeArtisan;
    }

    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->bindings)) {
            return $this->bindings[$abstract];
        }

        return match ($abstract) {
            'config' => $this->config,
            default => throw new RuntimeException(sprintf('Unknown abstract: %s', $abstract)),
        };
    }

    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory($this->laravelApp);
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function configPath(string $path = ''): string
    {
        return '/tmp/config'.($path !== '' ? '/'.$path : '');
    }

    public function databasePath(string $path = ''): string
    {
        return '/tmp/database'.($path !== '' ? '/'.$path : '');
    }

    public function basePath(string $path = ''): string
    {
        return '/tmp'.($path !== '' ? '/'.$path : '');
    }

    public function runningInConsole(): bool
    {
        return $this->runningInConsole;
    }

    public function configurationIsCached(): bool
    {
        return true;
    }

    public function getCachedConfigPath(): string
    {
        return '/tmp/bootstrap/cache/config.php';
    }

    public function getCachedServicesPath(): string
    {
        return '/tmp/bootstrap/cache/services.php';
    }

    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'artisan';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($offset === 'artisan') {
            return $this->artisan;
        }

        throw new RuntimeException('Unknown offset');
    }

    public function offsetSet(mixed $offset, mixed $value): void {}

    public function offsetUnset(mixed $offset): void {}
}

final class FakeArtisan
{
    /** @var array<int, Closure> */
    public array $callbacks = [];

    public function starting(Closure $callback): void
    {
        $this->callbacks[] = $callback;
    }
}

final class FakeConfig
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->values;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (! is_string($segment)) {
                throw new RuntimeException('Invalid configuration key');
            }

            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $last = array_shift($segments);
        if (! is_string($last)) {
            throw new RuntimeException('Invalid configuration key');
        }

        $target[$last] = $value;
    }
}
