<?php
declare(strict_types=1);

namespace Flux\Console\Command;

/**
 * Class CommandInterface
 * @package Flux\Console\Command
 */
interface CommandInterface
{
    const ARGUMENT_REQUIRED = 0;
    const ARGUMENT_OPTIONAL = 1;

    const OPTION_IS_BOOL = 0;
    const OPTION_HAS_VALUE = 1;

    /**
     *
     * @return void
     */
    public function configure();

    /**
     * @return int
     */
    public function execute(): int;

    public function showHelp();

    /**
     * @param string $longName
     * @param string|null $shortName
     * @param int $flag
     * @param string $usage
     * @return void
     */
    public function addOption(string $longName, ?string $shortName, int $flag = self::OPTION_IS_BOOL, string $usage = ''): void;

    /**
     * @param string $name
     * @param int $flag
     * @param string $usage
     * @return void
     */
    public function addArgument(string $name, int $flag = self::ARGUMENT_OPTIONAL, string $usage = '');

    /**
     * @param array $args
     * @return void
     */
    public function parseInput(array $args);

    /**
     * @return bool
     */
    public function verifyInput(): bool;

    /**
     * @param string $name
     * @return mixed
     */
    public function getOptionValue(string $name): mixed;

    /**
     * @param string $name
     * @return mixed
     */
    public function getArgumentValue(string $name): mixed;

    /**
     * @return string
     */
    public function getUsage(): string;

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name): void;

    /**
     * @param string|null $output
     * @param bool $newline
     */
    public function write(?string $output = null, bool $newline = false);

    /**
     * @param string $line
     * @return mixed
     */
    public function writeln(?string $line = null);


    /**
     * @param array $line
     * @return mixed
     */
    public function writelnTable(array $line);

    public function flushTableBuffer();

}

