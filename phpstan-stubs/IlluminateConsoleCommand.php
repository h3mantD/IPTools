<?php

declare(strict_types=1);

namespace Illuminate\Console;

abstract class Command
{
    public const SUCCESS = 0;

    /** @param  array<string, bool|string>  $arguments */
    final public function call(string $command, array $arguments = []): int
    {
        return self::SUCCESS;
    }

    final public function info(string $string, ?int $verbosity = null): void {}

    final public function line(string $string, ?string $style = null, ?int $verbosity = null): void {}

    final public function newLine(int $count = 1): static
    {
        return $this;
    }

    final public function option(string $key): mixed
    {
        return null;
    }
}
