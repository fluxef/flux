<?php
declare(strict_types=1);

namespace Flux\Console\Command;

use Flux\Console\Application;


/**
 * Class Version
 *
 * @package Flux\Console\Command
 */
class showVersion extends Command implements CommandInterface
{
    public function __construct()
    {

    }

    public function configure()
    {
        $this->addOption('verbose', 'v', self::OPTION_IS_BOOL, 'show verbose information');
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

        /** @var Application $app */
        $app = Application::getApplication();

        $this->write('Version ' . $app->getVersion());
        $this->writeln();

        return 0;
    }


}
