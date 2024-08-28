<?php
declare(strict_types=1);

namespace Flux\Console\Command;


abstract class Command implements CommandInterface
{

    protected string $name = '';

    protected array $optionnames = array();
    protected array $shortoptionnames = array();
    protected array $optionvalues = array();
    protected array $optionusage = array();

    protected array $argumentnames = array();
    protected array $argumentnamesindex = array();
    protected array $argumentvalues = array();

    protected int $argumentsrequired = 0;
    protected array $argumentusage = array();

    protected array $outputTableBuffer = array();

    abstract public function execute(): int;

    abstract public function showHelp(): void;


    public function configure(): void
    {

    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function addOption(string $longName, ?string $shortName, int $flag = self::OPTION_IS_BOOL, string $usage = ''): void
    {
        $longName = strtolower($longName);
        $this->optionnames[$longName] = $flag;

        if ($flag == self::OPTION_IS_BOOL)
            $this->optionvalues[$longName] = false;

        $u = '--' . $longName;

        if (!is_null($shortName)) {

            if (strlen($shortName) != 1) {
                $this->writeln("invalid short option: " . $shortName);
                exit(1);
            }

            $this->shortoptionnames[$shortName] = $longName;

            $u .= ',-' . $shortName;
        }
        $u .= " \t" . $usage;
        $this->optionusage[] = $u;

    }

    public function addArgument(string $name, int $flag = self::ARGUMENT_OPTIONAL, string $usage = ''): void
    {
        $name = strtolower($name);
        $this->argumentnames[$name] = $flag;
        $this->argumentnamesindex[] = $name;

        $a = array();
        if ($flag == self::ARGUMENT_REQUIRED) {
            $this->argumentsrequired++;
            $a['param'] = '<' . $name . '>';
            $a['usage'] = $usage;
        } else {
            $a['param'] = '[<' . $name . '>]';
            $a['usage'] = $usage;
        }

        $this->argumentusage[] = $a;
    }

    public function parseLongOption(string $arg)
    {
        $pos = strpos($arg, '=');

        if ($pos !== false) {
            $name = substr($arg, 0, $pos);
            $value = substr($arg, $pos + 1);
        } else {
            $name = $arg;
            $value = null;
        }

        $name = strtolower($name);

        if (!isset($this->optionnames[$name]))
            return;

        // so now we have an option
        if ($this->optionnames[$name] == self::OPTION_IS_BOOL) {
            $this->optionvalues[$name] = true;
            return;
        }

        // now we need a value

        if (is_null($value)) {
            $this->writeln("mandatory value for " . $name . " is not set");
            exit(1);
        }

        $this->optionvalues[$name] = $value;

    }

    public function parseShortOption(string $arg): void
    {
        if (strlen($arg) > 1) {
            $name = substr($arg, 0, 1);
            $value = substr($arg, 1);
        } else {
            $name = $arg;
            $value = null;
        }

        if (isset($this->shortoptionnames[$name]))
            $name = $this->shortoptionnames[$name];
        else
            return;     // not found


        if (!isset($this->optionnames[$name]))
            return;

        // so now we have an option
        if ($this->optionnames[$name] == self::OPTION_IS_BOOL) {
            $this->optionvalues[$name] = true;
            return;
        }

        // now we need a value

        if (is_null($value)) {
            $this->writeln("mandatory value for " . $name . " is not set");
            exit(1);
        }

        $this->optionvalues[$name] = $value;

    }


    public function parseInput(array $args): void
    {

        $params = array();

        foreach ($args as $arg) {

            if (strncmp('--', $arg, 2) == 0) {    // lange option
                $this->parseLongOption(substr($arg, 2));
                continue;
            }

            if (strncmp('-', $arg, 1) == 0) {    // kurzname option
                $this->parseShortOption(substr($arg, 1));
                continue;
            }

            $params[] = $arg;
        }

        $zaehler = 0;
        foreach ($params as $param) {
            if (!isset($this->argumentnamesindex[$zaehler]))
                return;

            $name = $this->argumentnamesindex[$zaehler];
            $this->argumentvalues[$name] = $param;
            $zaehler++;

        }

    }

    public function verifyInput(): bool
    {

        if ($this->argumentsrequired > count($this->argumentvalues)) {
            $this->writeln("error. not enough arguments");
            return false;
        }

        return true;
    }

    public function getOptionValue(string $name): mixed
    {
        if (!isset($this->optionvalues[$name]))
            return null;
        else
            return $this->optionvalues[$name];
    }


    public function getArgumentValue(string $name): mixed
    {
        if (!isset($this->argumentvalues[$name]))
            return null;
        else
            return $this->argumentvalues[$name];
    }


    public function getUsage(): string
    {
        $ret = 'Usage: ' . $this->name . ' ';
        $line = '';
        foreach ($this->argumentusage as $a) {
            $ret .= $a['param'] . " ";
            $line .= '   ' . $a['param'] . "\t " . $a['usage'] . "\n";
        }

        $ret .= "\n" . $line;

        foreach ($this->optionusage as $z)
            $ret .= '   ' . $z . "\n";

        return $ret;
    }


    public function write(?string $output = null, bool $newline = false): void
    {
        if (!empty($output))
            echo($output);

        if ($newline)
            echo("\n");
    }


    public function writeln(?string $line = null): void
    {
        $this->write($line, true);
    }


    public function writelnTable(array $line): void
    {
        $this->outputTableBuffer[] = $line;
    }

    public function flushTableBuffer(): void
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
            $this->writeln($z);
        }

        $this->outputTableBuffer = array();

    }
}

