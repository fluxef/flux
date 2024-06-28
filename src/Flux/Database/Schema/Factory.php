<?php
declare(strict_types=1);

namespace Flux\Database\Schema;

use Flux\Core\Core;
use Flux\Database\ConnectionPool;
use Flux\Database\DatabaseInterface;


class Factory
{
    public static function create(DatabaseInterface $db): SchemaInterface
    {
        $di = Core::getContainer();

        $logger = $di->get('logger');
        $config = $di->get('config');
        $pool = $di->get(ConnectionPool::class);

        $driver = $db->getDriverName();

        $SchemaClass = match ($driver) {
            'mysql' => MySQLSchema::class,
            'mysqli' => MySQLSchema::class,
            'pgsql' => PostgreSQLSchema::class,
// TODO           'sqlite' => SQLiteSchema::class,
            'default' => MySQLSchema::class
        };

        return new $SchemaClass($db, $logger, $pool, $config);
    }

}
