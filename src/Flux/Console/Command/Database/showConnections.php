<?php
declare(strict_types=1);

namespace Flux\Console\Command\Database;

use Flux\Console\Command\CommandInterface;
use Flux\Console\Command\Command;
use Flux\Logger\LoggerInterface;
use Flux\Database\ConnectionPool;

/**
 * Class showConnections
 * @package Flux\Console\Command\Database
 */
class showConnections extends Command implements CommandInterface
{
    public function __construct(protected LoggerInterface $logger, protected ConnectionPool $pool)
    {
    }

    public function configure()
    {
        $this->addOption('help', 'h', self::OPTION_IS_BOOL, 'show usage information');
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

        if ($this->getOptionValue('help') === true) {
            $this->showHelp();
            return 0;
        }

        if (!$this->verifyInput())
            return 1;

        $conf = $this->pool->getConfig();

        if (empty($conf)) {
            $this->writeln('the are no database-connections configured');
            return 0;
        }

        $this->writelnTable(array('Conn', 'Database', 'Type', 'Class', 'Flags'));
        $this->writelnTable(array('----', '--------', '----', '-----', '-----'));

        foreach ($conf as $name => $conn) {
            $extra = '';

            if (isset($conn['internal']))
                $extra .= 'Internal ';

            if ($this->pool->has($name))
                $extra .= 'Connected ';

            $this->writelnTable(array($name, $conn['database'], $conn['driver'], $conn['class'], $extra));
        }
        $this->flushTableBuffer();

        return 0;
    }


}
