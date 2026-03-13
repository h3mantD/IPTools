<?php

declare(strict_types=1);

use IPTools\Console\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        RecordingCommand::$calls = [];

        parent::tearDown();
    }

    public function test_install_command_publishes_assets_and_runs_migrations(): void
    {
        $command = new InstallCommand;
        $this->wireCommandForHandleExecution($command, []);

        $exitCode = $command->handle();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame([
            ['vendor:publish', ['tag' => 'iptools-config', 'force' => false]],
            ['vendor:publish', ['tag' => 'iptools-migrations', 'force' => false]],
            ['vendor:publish', ['tag' => 'iptools-model', 'force' => false]],
            ['migrate', ['force' => false]],
        ], RecordingCommand::$calls);
    }

    public function test_install_command_honors_force_and_no_migrate_flags(): void
    {
        $command = new InstallCommand;
        $this->wireCommandForHandleExecution($command, ['--force' => true, '--no-migrate' => true]);

        $exitCode = $command->handle();

        $this->assertSame(Command::SUCCESS, $exitCode);

        $this->assertSame([
            ['vendor:publish', ['tag' => 'iptools-config', 'force' => true]],
            ['vendor:publish', ['tag' => 'iptools-migrations', 'force' => true]],
            ['vendor:publish', ['tag' => 'iptools-model', 'force' => true]],
        ], RecordingCommand::$calls);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function wireCommandForHandleExecution(InstallCommand $command, array $options): void
    {
        $application = new SymfonyApplication;
        $application->setAutoExit(false);
        $application->add($command);
        $application->add(new RecordingCommand('vendor:publish'));
        $application->add(new RecordingCommand('migrate'));

        $input = new ArrayInput($options, $command->getDefinition());
        $output = new BufferedOutput;
        $style = new SymfonyStyle($input, $output);

        $inputProperty = new ReflectionProperty($command, 'input');
        $inputProperty->setValue($command, $input);

        $outputProperty = new ReflectionProperty($command, 'output');
        $outputProperty->setValue($command, $style);
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
