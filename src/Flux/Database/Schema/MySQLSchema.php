<?php
declare(strict_types=1);

namespace Flux\Database\Schema;

use Flux\Database\ConnectionPool;
use Flux\Database\DatabaseInterface;
use Flux\Logger\LoggerInterface;
use Flux\Config\Config;
use function strtolower;


class MySQLSchema extends AbstractSchema implements SchemaInterface
{

    public function __construct(protected DatabaseInterface $db, protected LoggerInterface $logger, protected ConnectionPool $pool, protected Config $config)
    {
    }


    protected function fetchTables(): array
    {
        $liste = $this->db->getlist('SHOW TABLE STATUS');

        if (empty($liste))
            return array();

        $ret = array();

        foreach ($liste as $table) {
            $t = array();
            $t['name'] = $table['Name'];
            $ret[] = $t;
        }

        return $ret;

    }

    protected function splitType(string $type): array
    {
        preg_match('/^(.+)\((.*)\)(.*)/', $type, $a);

        if (empty($a))
            return array('type' => $type, 'values' => array());

        $ret = array('type' => $a[1]);

        $p = str_replace("'", '', $a[2]);
        $prec = explode(',', $p);
        $ret['values'] = $prec;

        return $ret;

    }

    protected function fetchColumns(string $table): array
    {
        $liste = $this->db->getlist('SHOW FULL FIELDS FROM ' . $table);

        $erg = array();
        foreach ($liste as $row) {

            $st = $this->splitType($row['Type']);
            $f = array('name' => $row['Field']);

            // type indentifier conversion
            $type = match ($st['type']) {
                'int', 'mediumint', 'smallint', 'tinyint', 'int unsigned', 'smallint unsigned', 'tinyint unsigned', 'mediumint unsigned' => 'integer',
                'bigint', 'bigint unsigned' => 'biginteger',
                'decimal' => 'decimal',
                'varchar' => 'varchar',
                'date' => 'date',
                'enum' => 'enum',
                'tinytext', 'text', 'mediumtext', 'longtext' => 'text',
                'datetime' => 'datetime',
                'timestamp' => 'datetimetz',
                'json' => 'json',
                'tinyblob', 'mediumblob', 'blob', 'longblob' => 'blob',
                'char' => 'char',
                'float' => 'float',
                'time' => 'time',
                'double' => 'double',
                default => ''
            };

            if (empty($type))
                throw new MigrationException('invalid field type:' . $st['type'] . ' in table:' . $table . ' field:' . $f['name'], 1);

            $f['type'] = $type;     // replace mysql-type-name with normalized-json-config-type-name

            switch ($type) {
                case 'decimal':
                case 'float':
                    $f['precision'] = $st['values'][0];
                    $f['scale'] = $st['values'][1];
                    break;
                case 'enum':
                    $f['values'] = $st['values'];
                    break;
                case 'varchar':
                case 'char':
                    $f['length'] = $st['values'][0];
                    break;
                default:
            }


            if (!empty($row['Null']))
                if (strtolower($row['Null']) == "yes")
                    $f['nullable'] = true;

            if (isset($row['Default'])) {
                if (strtolower($row['Default']) == 'current_timestamp')
                    $f['default'] = 'current_timestamp';
                else
                    $f['default'] = $row['Default'];
            }

            if (!empty($row['Extra'])) {

                if (strtolower($row['Extra']) == 'auto_increment')
                    $f['autoincrement'] = true;

                if (strtolower($row['Extra']) == 'on update current_timestamp')
                    $f['onupdate'] = 'current_timestamp';

            }

            $erg[$f['name']] = $f;
        }
        return $erg;
    }

    protected function fetchConstraints(string $table): array
    {
        return array();     // we use no extra constraitns with mysql
    }

    protected function fetchIndexes(string $table): array
    {
        $erg = array();

        $liste = $this->db->getlist('SHOW INDEX FROM ' . $table);

        foreach ($liste as $row) {
            if (isset($erg[$row['Key_name']])) {    // additinally index columns of this index
                $erg[$row['Key_name']]['columns'][] = $row['Column_name'];
            } else {
                $f = array();
                $f['name'] = $row['Key_name'];

                if ($row['Key_name'] == 'PRIMARY') {
                    $f['primary'] = true;
                } elseif ($row['Non_unique'] == 0) {
                    $f['unique'] = true;
                }

                $co['name'] = $row['Column_name'];
                $f['columns'][] = $row['Column_name'];

                $erg[$row['Key_name']] = $f;
            }
        }

        return $erg;
    }


    protected function normalizeTableStructure(string $tablename, array $columns, array $constraints, array $indexes): array
    {
        // we do not need to normalize for mysql, so we just build and return the structure

        $f = array();
        $f['name'] = $tablename;

        $f['columns'] = $columns;
        $f['constraints'] = $constraints;
        $f['indexes'] = $indexes;

        return $f;
    }


    protected function createTableMigration(array $table): string
    {

        $query = 'CREATE TABLE `' . $table['name'] . "` (\n";
        $ko = '';

        foreach ($table['columns'] as $vf) {
            $query .= $ko . "    " . $this->FieldMigration($vf);
            if (empty($ko))
                $ko = ",\n";
        }

        foreach ($table['constraints'] as $name => $con)
            $query .= $ko . '   ADD CONSTRAINT ' . $name . ' ' . $this->ConstraintMigration($con, true);

        foreach ($table['indexes'] as $index)
            $query .= $ko . "    " . $this->IndexMigration($index);

        $query .= "\n);";

        return $query;
    }

    protected function createIndexMigration(array $table): array
    {
        return array();
    }

    protected function FieldMigration(array $field, bool $withname = true): string
    {

        // type indentifier conversion
        $type = match ($field['type']) {
            'integer' => 'int',
            'biginteger' => 'bigint',
            'decimal' => 'decimal',
            'varchar' => 'varchar',
            'date' => 'date',
            'enum' => 'enum',
            'text' => 'longtext',
            'datetime' => 'datetime',
            'datetimetz' => 'timestamp',
            'json' => 'json',
            'blob' => 'longblob',
            'char' => 'char',
            'float' => 'float',
            'time' => 'time',
            'double' => 'double',
            default => ''
        };

        if (empty($type))
            throw new MigrationException('invalid field type:' . $field['type'] . ' in field:' . $field['name'], 1);

        $ret = '';

        if ($withname)
            $ret .= '`' . $field['name'] . '`';

        $ret .= ' ' . $type;

        switch ($type) {
            case 'decimal':
                if (isset($field['precision']) && isset($field['scale'])) {
                    $ret .= '(' . $field['precision'] . ',' . $field['scale'] . ')';
                } elseif (isset($field['precision'])) {
                    $ret .= '(' . $field['precision'] . ')';
                }
                break;
            case 'varchar':
            case 'char':
                if (isset($field['length'])) {
                    $ret .= '(' . $field['length'] . ')';
                }
                break;
            case 'enum':
                if (isset($field['values'])) {
                    $ret .= '(';
                    $komma = '';
                    foreach ($field['values'] as $val) {
                        $ret .= $komma . "'" . $val . "'";
                        $komma = ',';
                    }
                    $ret .= ')';
                }
                break;
            default:
        }

        if (!isset($field['nullable']))     // null value is not allowed
            $ret .= ' NOT NULL';
        else {
            $ret .= ' NULL';            // null value allowed, but default value is also set
            if (!isset($field['default']))  // null value is allowed, but no default value is set
                $ret .= ' DEFAULT NULL';
        }

        if (isset($field["default"])) {
            if (strtolower($field['default']) == 'current_timestamp')
                $ret .= ' DEFAULT CURRENT_TIMESTAMP';
            else
                $ret .= " DEFAULT '" . $field["default"] . "'";
        }

        if (isset($field['autoincrement']))
            $ret .= ' AUTO_INCREMENT';

        if (isset($field['onupdate'])) {
            if (isset($field['default']) && (strtolower($field['default']) == 'current_timestamp'))
                $ret .= ' ON UPDATE CURRENT_TIMESTAMP';
            else
                $ret .= ' ON UPDATE ' . $field['onupdate'];
        }
        return $ret;
    }


    protected function ConstraintMigration(array $con, bool $add = false): string
    {
        return $con['definition'];
    }

    protected function IndexMigration(array $idx, bool $add = false): string
    {
        if ($idx['name'] == 'PRIMARY') {
            $ret = 'PRIMARY KEY';
        } else {
            if (isset($idx['unique']))
                $ret = 'UNIQUE';
            else
                $ret = 'INDEX';

            $ret .= ' `' . $idx['name'] . '`';
        }

        $ret .= ' (';

        $ko = '';

        foreach ($idx['columns'] as $vf) {
            $ret .= $ko . '`' . $vf . '`';
            if (empty($ko))
                $ko = ",";
        }

        $ret .= ')';

        return $ret;
    }


    protected function dropTableMigration(array $table): string
    {
        return ('DROP TABLE `' . $table['name'] . "`;");
    }


    protected function alterTableMigration(array $soll, array $ist): array
    {

        $ret = array();
        $alterindex = array();

        // first we migrate indexes

        foreach ($ist['indexes'] as $vk => $vf) {
            if (!isset($soll['indexes'][$vk])) { // index not in $soll, we drop the index
                if (!($vk == 'PRIMARY')) {
                    $ret[] = 'ALTER TABLE `' . $ist['name'] . '` DROP INDEX `' . $vk . "`;";
                }
            } else {
                // index in $soll and $ist, so we check if the sql-definition is changed
                $a = $this->IndexMigration($vf, true);
                $b = $this->IndexMigration($soll['indexes'][$vk], true);
                if (strcmp($a, $b) != 0) {  // something has changed
                    if (!($vk == 'PRIMARY')) {
                        $ret[] = 'ALTER TABLE `' . $ist['name'] . '` DROP INDEX `' . $vk . "`;"; // alten droppen
                    }
                    $alterindex[] = 'ADD ' . $b;    // remember for later
                }
            }
        }

        // now we go through all indexes in $soll to check if they exist in $ist
        foreach ($soll['indexes'] as $vk => $vf) {
            if (!isset($ist['indexes'][$vk])) { // index is in $soll, but not in $ist, so we have to add it
                $a = 'ADD ' . $this->IndexMigration($vf, true);
                $alterindex[] = $a;
            }
        }


        // now we check the columns

        $dropfields = array();
        $alterfields = array();

        foreach ($ist['columns'] as $vk => $vf) {
            if (!isset($soll['columns'][$vk])) // column is not in $soll, we drop it
                $dropfields[] = 'DROP `' . $vk . '`';
            else {
                // column in $soll and $ist, so we check if the sql-definition is changed
                $a = $this->FieldMigration($vf);
                $b = $this->FieldMigration($soll['columns'][$vk]);
                if (strcmp($a, $b) != 0)
                    $alterfields[] = 'MODIFY ' . $b;
            }
        }

        // now we go through all columns in $soll to check if they exist in $ist

        $prevfield = '';
        $insertfields = array();

        if (!empty($soll['columns']))
            foreach ($soll['columns'] as $vk => $vf) {
                if (!isset($ist['columns'][$vk])) { // column is not in $ist, so we add it
                    $a = 'ADD ' . $this->FieldMigration($vf);
                    if (!empty($prevfield))
                        $a .= ' AFTER ' . $prevfield;
                    $insertfields[] = $a;
                }
                $prevfield = $vk;
            }


        // now we should check the constraints (we ignore constraints in mysql
        // ALTER TABLE table_name DROP CONSTRAINT some_name;
        // ALTER TABLE prices_list ADD CONSTRAINT valid_range_check CHECK (valid_to >= valid_from);

        $cons = array();

        foreach ($ist['constraints'] as $name => $con) {
            if (!isset($soll['constraints'][$name])) // constraint is not in $soll, we drop it
                $cons[] = 'DROP CONSTRAINT ' . $name;
            else {
                // column in $soll and $ist, so we check if the sql-definition is changed
                $a = $this->ConstraintMigration($con);
                $b = $this->ConstraintMigration($soll['constraints'][$name]);
                if (strcmp($a, $b) != 0) {
                    $cons[] = 'DROP CONSTRAINT ' . $name;
                    $cons[] = 'ADD CONSTRAINT ' . $name . ' ' . $b;
                }
            }
        }

        // now we go through all constraints in $soll to check if they exist in $ist
        foreach ($soll['constraints'] as $name => $con) {
            if (!isset($ist['constraints'][$name])) { // constraints is in $soll, but not in $ist, so we have to add it
                $cons[] = 'ADD CONSTRAINT ' . $name . ' ' . $this->ConstraintMigration($con, true);
            }
        }

        // now we build the final sql-array with alter table statements
        // 1. drop fields
        // 2. add fields
        // 3. alter fields
        // 4. drop/add constraints
        // 5. add/drop/alter index


        $fields = $dropfields;
        foreach ($insertfields as $row)
            $fields[] = $row;

        foreach ($alterfields as $row)
            $fields[] = $row;

        foreach ($cons as $row)
            $fields[] = $row;

        foreach ($alterindex as $row)
            $fields[] = $row;

        if (!empty($fields)) {
            $ko = '';
            $re = 'ALTER TABLE `' . $ist['name'] . "`\n";
            foreach ($fields as $row) {
                $re .= $ko . '    ' . $row;
                $ko = ",\n";
            }
            $re .= ";";
            $ret[] = $re;
        }

        return $ret;

    }

}
