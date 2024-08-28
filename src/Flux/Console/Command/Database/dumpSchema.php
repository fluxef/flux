<?php
declare(strict_types=1);

namespace Flux\Console\Command\Database;

use Flux\Console\Application;
use Flux\Console\Command\CommandInterface;
use Flux\Console\Command\Command;
use Flux\Database\Schema\Factory;
use Flux\Logger\LoggerInterface;
use Flux\Database\ConnectionPool;


class dumpSchema extends Command implements CommandInterface
{
    public function __construct(protected LoggerInterface $logger, protected ConnectionPool $pool)
    {
    }

    public function configure(): void
    {
        $this->addArgument('ConnectionName', self::ARGUMENT_REQUIRED, 'Database connection name');
        $this->addOption('help', 'h', self::OPTION_IS_BOOL, 'show usage information');
        $this->addOption('write', 'w', self::OPTION_IS_BOOL, 'write schema file into config directory');

    }

    public function showHelp(): void
    {
        echo $this->getUsage() . "\n";
    }

    public function execute(): int
    {

        $di = Application::getContainer();

        if ($this->getOptionValue('help') === true) {
            $this->showHelp();
            return 0;
        }

        if (!$this->verifyInput())
            return 1;

        $ConnectionName = $this->getArgumentValue('connectionname');

        $conf = $this->pool->getConfig($ConnectionName);

        if (empty($conf)) {
            $this->writeln('no connection exists with the name: ' . $ConnectionName);
            return 0;
        }

        $conn = $di->get($ConnectionName);

        if (empty($conn)) {
            $this->writeln('no connection active with the name: ' . $ConnectionName);
            return 0;
        }

        $schema = Factory::create($conn);

        if (empty($schema)) {
            $this->writeln('can not create schema for connection: ' . $ConnectionName);
            return 0;
        }

        if ($this->getOptionValue('write') === true) {
            echo $schema->writeDump() . "\n";
        } else {
            echo $schema->toJSON($schema->getSchema()) . "\n";
        }
        return 0;
    }

}
