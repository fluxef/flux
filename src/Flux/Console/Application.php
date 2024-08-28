<?php
declare(strict_types=1);

namespace Flux\Console;

use Flux\Console\Command\CommandInterface;
use Flux\Core\Core as FluxCoreApplication;

class Application extends FluxCoreApplication
{
    protected array $cmdDescriptions = array();
    protected array $cmdClasses = array();

    private array $outputTableBuffer = array();

    public function addCommand(string $name, string $description, string $id, callable $callable)
    {

        $name = strtolower($name);  // always lowercase

        static::$ContainerInstance->set($id, $callable);    // register as service

        $this->cmdClasses[$name] = $id;
        $this->cmdDescriptions[$name] = $description;

    }

    private function writelnTable(array $line)
    {
        $this->outputTableBuffer[] = $line;
    }

    private function flushTableBuffer()
    {
        $maxlen = array();


        foreach ($this->outputTableBuffer as $line)
            foreach ($line as $column => $value) {

                $len = strlen($value);
                if (!isset($maxlen[$column]))
                    $maxlen[$column] = $len;

                if ($len > $maxlen[$column])
                    $maxlen[$column] = $len;

            }

        foreach ($this->outputTableBuffer as $line) {
            $z = '';
            foreach ($line as $column => $value)
                $z .= str_pad($value, $maxlen[$column] + 1);
            echo $z . "\n";
        }

        $this->outputTableBuffer = array();

    }

    public function showHelp(string $prefix = '')
    {
        $this->writelnTable(array('command', 'function'));
        $this->writelnTable(array('-------', '--------'));

        foreach ($this->cmdDescriptions as $cmd => $info) {
            if (!empty($prefix))
                if (strncmp($cmd, $prefix, strlen($prefix)) != 0)
                    continue;
            $this->writelnTable(array($cmd, $info));
        }

        $this->flushTableBuffer();

    }

    public function execute(): int
    {
        global $argv;
        global $argc;

        // get function
        if ($argc < 2) {
            $this->showHelp();
            return 0;
        }

        $cmd = strtolower($argv[1]);

        if (substr($cmd, -1) == '?') {
            $this->showHelp(substr($cmd, 0, -1));
            return 0;
        }

        if (!$this->has($this->cmdClasses[$cmd])) {
            echo "command '" . $cmd . "' not found\n";
            return 1;
        }

        /** @var CommandInterface $cla */
        $cla = $this->get($this->cmdClasses[$cmd]);

        $cla->setName($argv[0] . ' ' . $cmd);

        $cla->configure();
        $bla = $argv;
        array_shift($bla);
        array_shift($bla);
        $cla->parseInput($bla);

        return $cla->execute();

    }


}
