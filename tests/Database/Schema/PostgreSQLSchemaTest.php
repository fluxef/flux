<?php
declare(strict_types=1);

namespace Flux\Database\Tests\Schema;

use Flux\Config\Config;
use Flux\Database\ConnectionPool;
use Flux\Database\Driver\PDOMySQL;
use Flux\Database\Schema\PostgreSQLSchema;
use Flux\Logger\Logger;
use PHPUnit\Framework\TestCase;

class PostgreSQLSchemaTest extends TestCase
{

    protected array $db_tables = array(
        array('table_catalog' => 'testdb',
            'table_schema' => 'public',
            'table_name' => 'accounts',
            'table_type' => 'BASE TABLE',
            'self_referencing_column_name' => '',
            'reference_generation' => '',
            'user_defined_type_catalog' => '',
            'user_defined_type_schema' => '',
            'user_defined_type_name' => '',
            'is_insertable_into' => 'YES',
            'is_typed' => 'NO',
            'Create_options' => '',
            'commit_action' => ''
        ),
        array('table_catalog' => 'testdb',
            'table_schema' => 'public',
            'table_name' => 'balances',
            'table_type' => 'BASE TABLE',
            'self_referencing_column_name' => '',
            'reference_generation' => '',
            'user_defined_type_catalog' => '',
            'user_defined_type_schema' => '',
            'user_defined_type_name' => '',
            'is_insertable_into' => 'YES',
            'is_typed' => 'NO',
            'Create_options' => '',
            'commit_action' => ''
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

        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);
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

        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);
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
            $this->db_tables, array(), array(), array(), array(), array(), array(), array(), array(), array(), array()
        );

        $output = array(
            'accounts' => array('name' => 'accounts', 'columns' => array(), 'constraints' => array(), 'indexes' => array()),
            'balances' => array('name' => 'balances', 'columns' => array(), 'constraints' => array(), 'indexes' => array())
        );

        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);
        $result = $schema->getSchema();

        $this->assertEquals($output, $result);

    }

    public function testloadDump()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);

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
                        'type' => 'text',
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
                        'onupdate' => 'current_timestamp'
                    ]
                ],
                'constraints' => [
                    'usertable_pkey' => [
                        'name' => 'usertable_pkey',
                        'columns' => ['id'],
                        'type' => 'primary',
                        'definition' => 'PRIMARY KEY (id)'
                    ],
                    'usertable_username_unique' => [
                        'name' => 'usertable_username_unique',
                        'columns' => ['username'],
                        'type' => 'unique',
                        'definition' => 'UNIQUE (username)'
                    ],
                    'usertable_gender_check' => [
                        'name' => 'usertable_gender_check',
                        'columns' => ['gender'],
                        'type' => 'check',
                        'definition' => "CHECK ((gender = ANY (ARRAY['male'::text, 'female'::text, 'other'::text])))"
                    ]
                ],
                'indexes' => [
                    'usertable_email' => [
                        'name' => 'usertable_email',
                        'columns' => ['email']
                    ],
                    'usertable_gendercreated' => [
                        'name' => 'usertable_gendercreated',
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
        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);

        $result = $schema->toString($schema->getMigrationScript(__DIR__ . '/db.json'));

        $output = "CREATE TABLE usertable (
   id serial NOT NULL,
   username varchar(64) NOT NULL DEFAULT '',
   gender text NOT NULL DEFAULT 'other',
   email varchar(255) NULL,
   usercreated timestamp with time zone NOT NULL DEFAULT NOW(),
   userchanged timestamp with time zone NOT NULL DEFAULT NOW(),
   CONSTRAINT usertable_pkey PRIMARY KEY (id),
   CONSTRAINT usertable_username_unique UNIQUE (username),
   CONSTRAINT usertable_gender_check CHECK ((gender = ANY (ARRAY['male'::text, 'female'::text, 'other'::text])))
);
CREATE INDEX usertable_email ON usertable(email);
CREATE INDEX usertable_gendercreated ON usertable(gender,usercreated);
";

        $this->assertEquals(trim($output), trim($result));

    }


    public function testCreateMigration()
    {
        $logger = $this->createMock(Logger::class);
        $pool = $this->createMock(ConnectionPool::class);
        $conf = $this->createMock(Config::class);
        $db = $this->createMock(PDOMySQL::class);
        $schema = new PostgreSQLSchema($db, $logger, $pool, $conf);


        $soll = $schema->loadDump(__DIR__ . '/soll.json');
        $ist = $schema->loadDump(__DIR__ . '/postgres-ist.json');

        $result = $schema->toString($schema->createMigration($soll, $ist));

        $expected = "CREATE TABLE suppliers (
   id serial NOT NULL,
   companyname int NOT NULL,
   CONSTRAINT suppliers_pkey PRIMARY KEY (id)
);
CREATE INDEX suppliers_companyname ON suppliers(companyname);
DROP TABLE orders;
ALTER TABLE customers
    DROP COLUMN lastorder,
    ADD COLUMN firstname varchar(200) NULL;
CREATE INDEX customers_firstname ON customers(firstname,lastname);";

        $this->assertEquals(trim($expected), trim($result));

    }
}
