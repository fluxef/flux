<?php
declare(strict_types=1);

namespace Flux\Database;

use Flux\Database\Driver\PDOPostgreSQL;

use Flux\Database\Driver\PDOMySQL;
use Flux\Database\Driver\PDOSQLite;

class ConnectionPool
{

    protected array $drivers = array();
    protected array $configs = array();

    public function has(string $conname): bool
    {
        return isset($this->drivers[$conname]);
    }

    public function get(string $conname): ?DatabaseInterface
    {
        if (isset($this->drivers[$conname]))
            return $this->drivers[$conname];
        else
            return null;
    }

    public function set(string $conname, DatabaseInterface $db)
    {
        $this->drivers[$conname] = $db;
    }

    public function getDefaultConfigEntry(string $driver): array
    {

        $class = match ($driver) {
            'mysql' => PDOMySQL::class,
            'pgsql' => PDOPostgreSQL::class,
            'sqlite' => PDOSQLite::class,
        };

        if (empty($class))
            return array();

        if ($driver == 'mysql')
            return array(
                'driver' => $driver,
                'class' => $class,
                'host' => 'localhost',
                'port' => 3306,
                'database' => '',
                'user' => '',
                'password' => '',
                'charset' => 'utf8mb4',
                'debug' => false,
                'options' => array(),
                'dsn' => '',
                'lazyloading' => true
            );

        if ($driver == 'pgsql')
            return array(
                'driver' => $driver,
                'class' => $class,
                'host' => 'localhost',
                'port' => 5432,
                'database' => '',
                'user' => '',
                'password' => '',
                'charset' => 'utf8',
                'debug' => false,
                'options' => array(),
                'dsn' => '',
                'lazyloading' => true
            );

        if ($driver == 'sqlite')
            return array(
                'driver' => $driver,
                'class' => $class,
                'database' => '',
                'debug' => false,
                'options' => array(),
                'dsn' => '',
                'lazyloading' => true
            );

        return array();
    }

    public function loadConfigFromFile(string $filename = '', string $storagepath = '')
    {
        if (empty($filename))
            return;

        if (!file_exists($filename))
            return;

        $content = file_get_contents($filename);
        $conf = json_decode($content, true);

        if (empty($conf))
            return;

        if (empty($conf['connections']))
            return;

        $Connections = $conf['connections'];

        $this->configs = array();
        $this->drivers = array();
        $first = true;

        foreach ($Connections as $ConName => $Config) {

            // if DSN ist set: extract driver name from DSN-prefix
            // but only for supported database-types
            if (!empty($Config['dsn'])) {
                if (strncmp($Config['dsn'], 'mysql:', 6) == 0) {
                    $Config['driver'] = 'mysql';
                } elseif (strncmp($Config['dsn'], 'pgsql:', 6) == 0) {
                    $Config['driver'] = 'pgsql';
                } elseif (strncmp($Config['dsn'], 'sqlite:', 7) == 0) {
                    $Config['driver'] = 'sqlite';
                }

            }

            if (empty($Config['driver']))
                continue;

            if ($Config['driver'] == 'sqlite') {
                if (strncmp($Config['database'], '/', 1) != 0)
                    $Config['database'] = $storagepath . $Config['database'];
            }

            $default = $this->getDefaultConfigEntry($Config['driver']);

            $this->configs[$ConName] = array_merge($default, $Config);
            if (($first) && ($ConName == 'db')) {
                $this->configs[$ConName]['internal'] = true;
                $first = false;
            } else {
                unset($this->configs[$ConName]['internal']);
            }

        }

    }

    public function getConfig(?string $ConnName = null): array
    {
        if (is_null($ConnName))
            return $this->configs;
        if (isset($this->configs[$ConnName]))
            return $this->configs[$ConnName];

        return array();
    }

    public function getConnections(): array
    {
        return $this->drivers;
    }

}
