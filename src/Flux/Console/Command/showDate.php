<?php
declare(strict_types=1);

namespace Flux\Console\Command;

/**
 * Class Version
 * @package Flux\Console\Command
 */
class showDate extends Command implements CommandInterface
{
    public function __construct()
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

        $d = new DateTime('now');
        $this->writeln($d->format('c'));

        return 0;
    }

}
