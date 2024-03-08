<?php
declare(strict_types=1);

namespace Flux\Console\Command\Database;

use Flux\Console\Application;
use Flux\Console\Command\CommandInterface;
use Flux\Console\Command\Command;
use Flux\Database\Schema\Factory;
use Flux\Logger\LoggerInterface;
use Flux\Database\ConnectionPool;


class migrateSchema extends Command implements CommandInterface
{
    public function __construct(protected LoggerInterface $logger, protected ConnectionPool $pool)
    {
    }

    public function configure()
    {
        $this->addArgument('ConnectionName', self::ARGUMENT_REQUIRED, 'Database connection name');
        $this->addOption('help', 'h', self::OPTION_IS_BOOL, 'show usage information');
        $this->addOption('migrate', 'm', self::OPTION_IS_BOOL, 'do migration in database');
    }

    public function showHelp()
    {
        echo $this->getUsage() . "\n";
    }

    /**
     * @return int
     */
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

        $sqlarr = $schema->getMigrationScript();

        if (is_null($sqlarr)) {
            $this->writeln("can not create migration script. database update failed!");
            $this->logger->critical('can not create migration script. database update failed');
            return 1;
        }

        if ($this->getOptionValue('migrate') === true) {
            $good = 0;
            $bad = 0;

            foreach ($sqlarr as $sql) {
                if (!empty($sql)) {
                    if ($conn->statement($sql)) {
                        $good++;
                    } else {
                        $bad++;
                    }
                } else {
                    $bad++;
                }
            }

            if (($good + $bad) == 0)
                $this->writeln('no difference in database schema');
            else
                $this->writeln('Updates without error:' . $good . ', Updates with error:' . $bad);


        } else {
            $this->writeln("# start migration database: " . $conn->getDBName());
            foreach ($sqlarr as $s)
                $this->writeln($s);
            $this->writeln("# end migration database: " . $conn->getDBName());

        }


        return 0;

    }

}
