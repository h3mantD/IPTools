<?php

declare(strict_types=1);

namespace IPTools;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use IPTools\Console\InstallCommand;
use IPTools\Storage\LaravelRangeStorage;
use IPTools\Storage\RangeStorageInterface;

final class IPToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/iptools.php', 'iptools');

        $this->app->singleton(LaravelRangeStorage::class, function (Application $app): LaravelRangeStorage {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            $connectionValue = $config->get('iptools.storage.connection');
            $connectionName = is_string($connectionValue) && $connectionValue !== ''
                ? $connectionValue
                : null;

            $tableValue = $config->get('iptools.storage.table', 'ip_ranges');
            $table = is_string($tableValue) && $tableValue !== ''
                ? $tableValue
                : 'ip_ranges';

            /** @var DatabaseManager $database */
            $database = $app->make('db');

            /** @var Connection $connection */
            $connection = $database->connection($connectionName);

            return new LaravelRangeStorage($connection, $table);
        });

        $this->app->alias(LaravelRangeStorage::class, RangeStorageInterface::class);
    }

    public function boot(): void
    {
        $configPath = $this->resolveConfigPath();
        $modelPath = $this->resolveModelPath();

        $this->publishes([
            __DIR__.'/../config/iptools.php' => $configPath,
        ], 'iptools-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'iptools-migrations');

        $this->publishes([
            __DIR__.'/../stubs/IpRange.php.stub' => $modelPath,
        ], 'iptools-model');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    private function resolveConfigPath(): string
    {
        return $this->app->configPath('iptools.php');
    }

    private function resolveModelPath(): string
    {
        return (string) $this->app->basePath('app/Models/IpRange.php');
    }
}
