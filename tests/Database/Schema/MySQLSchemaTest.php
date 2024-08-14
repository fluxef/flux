<?php
declare(strict_types=1);

namespace Flux\Database\Tests\Schema;

use Flux\Config\Config;
use Flux\Database\ConnectionPool;
use Flux\Database\Driver\PDOMySQL;
use Flux\Database\Schema\MySQLSchema;
use Flux\Logger\Logger;
use PHPUnit\Framework\TestCase;

class MySQLSchemaTest extends TestCase
{

    protected array $db_tables = array(
        array('Name' => 'accounts',
            'Engine' => 'InnoDB',
            'Version' => 10,
            'Row_format' => 'Dynamic',
            'Rows' => 0,
            'Avg_row_length' => 0,
            'Data_length' => 16384,
            'Max_data_length' => 0,
            'Check_time' => false,
            'Collation' => 'utf8mb4_unicode_ci',
            'Checksum' => false,
            'Create_options' => '',
            'Comment' => ''
        ),
        array('Name' => 'balances',
            'Engine' => 'InnoDB',
            'Version' => 10,
            'Create_options' => '',
            'Comment' => ''
        ),
    );

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     * @throws ReflectionException
     */
    public function invokeMethod(object &$object, string $methodName, array $parameters = array()): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testfetchTables()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);


        $output = array(array('name' => 'accounts'), array('name' => 'balances'));

        $db = $this->createMock(PDOMySQL::class);
        $db->method("getlist")->willReturn($this->db_tables);

        $schema = new MySQLSchema($db, $logger, $pool, $conf);
        $result = $this->invokeMethod($schema, 'fetchTables');

        $this->assertEquals($output, $result);

    }

    public function testgetSchemaEmptyDatabase()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $db->method("getlist")->willReturn(array());

        $output = array();

        $schema = new MySQLSchema($db, $logger, $pool, $conf);
        $result = $schema->getSchema();

        $this->assertEquals($output, $result);


    }

    public function testgetSchemaEmptyTables()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $db->method("getlist")->willReturn(
            $this->db_tables, array(), array(), array(), array()
        );

        $output = array(
            'accounts' => array('name' => 'accounts', 'columns' => array(), 'constraints' => array(), 'indexes' => array()),
            'balances' => array('name' => 'balances', 'columns' => array(), 'constraints' => array(), 'indexes' => array())
        );

        $schema = new MySQLSchema($db, $logger, $pool, $conf);
        $result = $schema->getSchema();

        $this->assertEquals($output, $result);

    }

    public function testloadDump()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $schema = new MySQLSchema($db, $logger, $pool, $conf);

        $result = $schema->loadDump(__DIR__ . '/db.json');

        $output = [
            'usertable' => [
                'name' => 'usertable',
                'columns' => [
                    'id' => ['name' => 'id',
                        'type' => 'integer',
                        'autoincrement' => 1
                    ],
                    'username' => [
                        'name' => 'username',
                        'type' => 'varchar',
                        'length' => 64,
                        'default' => ''
                    ],
                    'gender' => [
                        'name' => 'gender',
                        'type' => 'enum',
                        'values' => ['male', 'female', 'other'],
                        'default' => 'other'
                    ],
                    'email' => [
                        'name' => 'email',
                        'type' => 'varchar',
                        'length' => 255,
                        'nullable' => 1
                    ],
                    'usercreated' => [
                        'name' => 'usercreated',
                        'type' => 'datetimetz',
                        'default' => 'current_timestamp'
                    ],
                    'userchanged' => [
                        'name' => 'userchanged',
                        'type' => 'datetimetz',
                        'default' => 'current_timestamp',
                        'onupdate' => 'current_timestamp',

                    ]
                ],
                'constraints' => [],
                'indexes' => [
                    'PRIMARY' => [
                        'name' => 'PRIMARY',
                        'primary' => 1,
                        'columns' => ['id']
                    ],
                    'username' => [
                        'name' => 'username',
                        'unique' => 1,
                        'columns' => ['username']
                    ],
                    'email' => [
                        'name' => 'email',
                        'columns' => ['email']
                    ],
                    'gendercreated' => [
                        'name' => 'gendercreated',
                        'columns' => ['gender', 'usercreated']
                    ],

                ]

            ]
        ];

        $this->assertEquals($output, $result);

    }

    public function testgetMigrationScriptCreateTable()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $schema = new MySQLSchema($db, $logger, $pool, $conf);

        $result = $schema->toString($schema->getMigrationScript(__DIR__ . '/db.json'));

        $expected = "CREATE TABLE `usertable` (
    `id` int NOT NULL AUTO_INCREMENT,
    `username` varchar(64) NOT NULL DEFAULT '',
    `gender` enum('male','female','other') NOT NULL DEFAULT 'other',
    `email` varchar(255) NULL DEFAULT NULL,
    `usercreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `userchanged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE `username` (`username`),
    INDEX `email` (`email`),
    INDEX `gendercreated` (`gender`,`usercreated`)
);
";

        $this->assertEquals(trim($expected), trim($result));

    }

    public function testCreateMigration()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $schema = new MySQLSchema($db, $logger, $pool, $conf);


        $soll = $schema->loadDump(__DIR__ . '/soll.json');
        $ist = $schema->loadDump(__DIR__ . '/mysql-ist.json');

        $result = $schema->toString($schema->createMigration($soll, $ist));

        $expected = 'CREATE TABLE `suppliers` (
    `id` int NOT NULL AUTO_INCREMENT,
    `companyname` int NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `companyname` (`companyname`)
);
ALTER TABLE `customers`
    DROP `lastorder`,
    ADD `firstname` varchar(200) NULL DEFAULT NULL AFTER customernumber,
    ADD INDEX `firstname` (`firstname`,`lastname`);
DROP TABLE `orders`;';

        $this->assertEquals(trim($expected), trim($result));

    }
}
