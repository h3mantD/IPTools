<?php

declare(strict_types=1);

namespace IPTools\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'iptools:install {--force : Overwrite any existing published files} {--no-migrate : Skip running migrations}';

    /** @var string */
    protected $description = 'Install IPTools package assets and run migrations';

    public function handle(): int
    {
        $this->info('Publishing IPTools config...');
        $this->call('vendor:publish', $this->publishArguments('iptools-config'));

        $this->info('Publishing IPTools migration...');
        $this->call('vendor:publish', $this->publishArguments('iptools-migrations'));

        $this->info('Publishing optional IPTools model...');
        $this->call('vendor:publish', $this->publishArguments('iptools-model'));

        if (! $this->option('no-migrate')) {
            $this->info('Running database migrations...');
            $this->call('migrate', ['--force' => (bool) $this->option('force')]);
        }

        $this->newLine();
        $this->info('IPTools installation complete.');
        $this->line('Next step example:');
        $this->line('  $storage = app(\IPTools\Storage\RangeStorageInterface::class);');

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool|string>
     */
    private function publishArguments(string $tag): array
    {
        $arguments = ['--tag' => $tag];

        if ((bool) $this->option('force')) {
            $arguments['--force'] = true;
        }

        return $arguments;
    }
}
