<?php
declare(strict_types=1);

namespace Flux\Console\Command;

interface CommandInterface
{
    const int ARGUMENT_REQUIRED = 0;
    const int ARGUMENT_OPTIONAL = 1;

    const int OPTION_IS_BOOL = 0;
    const int OPTION_HAS_VALUE = 1;

    public function configure(): void;

    public function execute(): int;

    public function showHelp(): void;

    public function addOption(string $longName, ?string $shortName, int $flag = self::OPTION_IS_BOOL, string $usage = ''): void;

    public function addArgument(string $name, int $flag = self::ARGUMENT_OPTIONAL, string $usage = ''): void;

    public function parseInput(array $args): void;

    public function verifyInput(): bool;

    public function getOptionValue(string $name): mixed;

    public function getArgumentValue(string $name): mixed;

    public function getUsage(): string;

    public function setName(string $name): void;

    public function write(?string $output = null, bool $newline = false): void;

    public function writeln(?string $line = null): void;

    public function writelnTable(array $line): void;

    public function flushTableBuffer();

}

