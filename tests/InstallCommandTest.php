<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use IPTools\Console\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        RecordingCommand::$calls = [];

        parent::tearDown();
    }

    public function test_install_command_publishes_assets_and_runs_migrations(): void
    {
        $application = $this->createConsoleApplication();

        $tester = new CommandTester($application->find('iptools:install'));
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            ['vendor:publish', ['tag' => 'iptools-config', 'force' => false]],
            ['vendor:publish', ['tag' => 'iptools-migrations', 'force' => false]],
            ['vendor:publish', ['tag' => 'iptools-model', 'force' => false]],
            ['migrate', ['force' => false]],
        ], RecordingCommand::$calls);
    }

    public function test_install_command_honors_force_and_no_migrate_flags(): void
    {
        $application = $this->createConsoleApplication();

        $tester = new CommandTester($application->find('iptools:install'));
        $tester->execute(['--force' => true, '--no-migrate' => true]);

        $this->assertSame([
            ['vendor:publish', ['tag' => 'iptools-config', 'force' => true]],
            ['vendor:publish', ['tag' => 'iptools-migrations', 'force' => true]],
            ['vendor:publish', ['tag' => 'iptools-model', 'force' => true]],
        ], RecordingCommand::$calls);
    }

    private function createConsoleApplication(): SymfonyApplication
    {
        $application = new SymfonyApplication;
        $application->setAutoExit(false);

        $install = new InstallCommand;
        $install->setLaravel(new FakeConsoleContainer);
        $application->add($install);
        $application->add(new RecordingCommand('vendor:publish'));
        $application->add(new RecordingCommand('migrate'));

        return $application;
    }
}

final class RecordingCommand extends Command
{
    /** @var array<int, array{0: string, 1: array<string, mixed>}> */
    public static array $calls = [];

    public function __construct(private readonly string $name)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        if ($this->name === 'vendor:publish') {
            $this->addOption('tag');
            $this->addOption('force');

            return;
        }

        if ($this->name === 'migrate') {
            $this->addOption('force');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->name === 'vendor:publish') {
            self::$calls[] = [
                $this->name,
                [
                    'tag' => $input->getOption('tag'),
                    'force' => (bool) $input->getOption('force'),
                ],
            ];

            return Command::SUCCESS;
        }

        self::$calls[] = [
            $this->name,
            [
                'force' => (bool) $input->getOption('force'),
            ],
        ];

        return Command::SUCCESS;
    }
}

final class FakeConsoleContainer
{
    public function runningUnitTests(): bool
    {
        return true;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if ($abstract === OutputStyle::class) {
            return new OutputStyle($parameters['input'], $parameters['output']);
        }

        if ($abstract === Factory::class) {
            return new class {};
        }

        throw new RuntimeException(sprintf('Unsupported abstract: %s', $abstract));
    }

    public function call(callable $callback): mixed
    {
        return $callback();
    }
}
