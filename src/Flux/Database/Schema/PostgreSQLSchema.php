<?php
declare(strict_types=1);

namespace Flux\Database\Schema;

use Flux\Database\ConnectionPool;
use Flux\Database\DatabaseInterface;
use Flux\Logger\LoggerInterface;
use Flux\Config\Config;

class PostgreSQLSchema extends AbstractSchema implements SchemaInterface
{

    public function __construct(protected DatabaseInterface $db, protected LoggerInterface $logger, protected ConnectionPool $pool, protected Config $config)
    {
    }

    protected function fetchTables(): array
    {
        $liste = $this->db->getlist("SELECT * FROM information_schema.tables WHERE table_schema='public';");

        if (empty($liste))
            return array();

        $ret = array();

        foreach ($liste as $table) {
            $t = array();
            $t['name'] = $table['table_name'];
            $ret[] = $t;
        }
        return $ret;
    }

    protected function fetchColumns(string $table): array
    {

        $liste = $this->db->getlist("SELECT * FROM information_schema.columns WHERE table_schema='public' AND table_name = '" . $table . "';");

        $erg = array();

        foreach ($liste as $row) {

            $f = array();
            $f['name'] = $row['column_name'];

            // type indentifier conversion
            $type = match ($row['data_type']) {
                'character varying' => 'varchar',
                'integer' => 'integer',
                'timestamp without time zone' => 'datetime',
                'timestamp with time zone' => 'datetimetz',
                'date' => 'date',
                'bigint' => 'biginteger',
                'text' => 'text',
                'json' => 'json',
                'bytea' => 'blob',
                'character' => 'char',
                'real' => 'float',
                'double precision' => 'double',
                'time without time zone' => 'time',
                'decimal', 'numeric' => 'decimal',
                default => ''
            };

            if (empty($type))
                throw new MigrationException('invalid field type:' . $row['data_type'] . ' in table:' . $table . ' field:' . $f['name'], 1);

            $f['type'] = $type;     // replace mysql-type-name with normalized-json-config-type-name

            switch ($type) {
                case 'decimal':
                    $f['precision'] = $row['numeric_precision'];
                    $f['scale'] = $row['numeric_scale'];
                    break;
                case 'varchar':
                case 'char':
                    $f['length'] = $row['character_maximum_length'];
                    break;
                default:
            }

            if (!empty($row['is_nullable']))
                if ($row['is_nullable'] == "YES")
                    $f['nullable'] = true;

            if (isset($row['column_default'])) {
                // check if auto-increment
                // pattern nextval('roles_role_id_seq'::regclass)
                $cmp = "nextval('" . $table . '_' . $f['name'] . "_seq'::regclass)";

                if (strcmp($cmp, $row['column_default']) == 0) {
                    $f['autoincrement'] = true;
                } else {
                    // check if we have a   'value'::character varying    pattern
                    // and remove it to extract the default value
                    $def = str_replace('::character varying', '', $row['column_default']);  // varchar
                    $def = str_replace('::text', '', $def);       // text
                    $def = str_replace('::bpchar', '', $def);       // text
                    $def = str_replace('::real', '', $def);       // text
                    $def = str_replace('::double precision', '', $def);       // text
                    $def = str_replace('::bigint', '', $def);       // text

                    $def = trim($def, "'");
                    $f['default'] = $def;
                }
            }

            $erg[$f['name']] = $f;
        }
        return $erg;
    }

    protected function fetchConstraints(string $table): array
    {

        $sql = "select pgc.conname as constraint_name,
       ccu.table_schema as table_schema,
       ccu.table_name,
       ccu.column_name,
       contype,
        pg_get_constraintdef(pgc.oid)
        from pg_constraint pgc
         join pg_namespace nsp on nsp.oid = pgc.connamespace
         join pg_class  cls on pgc.conrelid = cls.oid
         left join information_schema.constraint_column_usage ccu
                   on pgc.conname = ccu.constraint_name
                       and nsp.nspname = ccu.constraint_schema
        WHERE ccu.table_name='" . $table . "'
        order by pgc.conname;";


        $liste = $this->db->getlist($sql);

        $erg = array();
        foreach ($liste as $row) {
            if (isset($erg[$row['constraint_name']])) {
                // we already have a constraint with this name, so we only add the column name
                $erg[$row['constraint_name']]['columns'][] = $row['column_name'];
            } else {
                $f = array('name' => $row['constraint_name']);
                $f['columns'][] = $row['column_name'];
                $f['type'] = match ($row['contype']) {
                    'p' => 'primary',
                    'u' => 'unique',
                    'c' => 'check',
                    default => $row['conntype']
                };
                $f['definition'] = $row['pg_get_constraintdef'];
                $erg[$row['constraint_name']] = $f;
            }


        }

        return $erg;
    }


    protected function fetchIndexes(string $table): array
    {

        $erg = array();

        $liste = $this->db->getlist("SELECT indexname,indexdef,* FROM pg_indexes WHERE schemaname='public' AND tablename = '" . $table . "'");

        if (empty($liste))
            return array();

        foreach ($liste as $row) {

            $f = array('name' => $row['indexname']);

            if (strncmp('create unique', strtolower($row['indexdef']), 13) == 0)
                $f['unique'] = true;

            preg_match('/^(.+)\((.*)\)(.*)/', $row['indexdef'], $a);
            $fields = array();
            foreach (explode(',', $a[2]) as $field)
                $fields[] = trim($field);
            $f['columns'] = $fields;

            $erg[$f['name']] = $f;

        }

        return $erg;
    }


    protected function normalizeTableStructure(string $tablename, array $columns, array $constraints, array $indexes): array
    {

        // if we have an index named 'PRIMARY' we add a primary key constraint and remove this index
        if (isset($indexes['PRIMARY'])) {
            $prima = $indexes['PRIMARY'];
            unset($indexes['PRIMARY']);

            $con = array();
            $con['name'] = $tablename . '_pkey';
            $con['columns'] = $prima['columns'];
            $con['type'] = 'primary';
            $d = 'PRIMARY KEY (';
            $k = '';
            foreach ($con['columns'] as $v) {
                $d .= $k . $v;
                $k = ', ';
            }
            $d .= ')';
            $con['definition'] = $d;
            $constraints[$con['name']] = $con;
        }

        // if we have primary/unique-constraints that have the same names as indexes, we remove the index (it is then an automatic created index)
        foreach ($constraints as $name => $con) {
            if (($con['type'] != 'primary') && ($con['type'] != 'unique'))
                continue;

            if (isset($indexes[$name]))
                unset($indexes[$name]);
        }

        // if we have unique indexes we convert them to constraints
        $indexnames = array();
        foreach ($indexes as $name => $index) {
            if (!isset($index['unique']))
                continue;
            $indexnames[] = $name;

            $con = array();
            $con['name'] = $tablename . '_' . $index['columns'][0] . '_unique';
            $con['columns'] = $index['columns'];
            $con['type'] = 'unique';
            $d = 'UNIQUE (';
            $k = '';
            foreach ($con['columns'] as $v) {
                $d .= $k . $v;
                $k = ', ';
            }
            $d .= ')';
            $con['definition'] = $d;
            $constraints[$con['name']] = $con;
        }

        foreach ($indexnames as $name)
            unset($indexes[$name]);


        // if we have enum fields we create a check constraint and make the file a text field
        $enumcolnames = array();
        foreach ($columns as $name => $col) {
            if ($col['type'] != 'enum')
                continue;
            $enumcolnames[] = $name;

            $con = array();
            $con['name'] = $tablename . '_' . $name . '_check';
            $con['columns'] = array($name);
            $con['type'] = 'check';

            // example: CHECK ((gender_check = ANY (ARRAY['male'::text, 'female'::text])))
            $d = 'CHECK ((' . $name . ' = ANY (ARRAY[';
            $k = '';
            foreach ($col['values'] as $v) {
                $d .= $k . "'" . $v . "'::text";
                $k = ', ';
            }
            $d .= '])))';
            $con['definition'] = $d;

            $constraints[$con['name']] = $con;
        }

        // we don't want change the structure in the foreach-loop, so we have to remember the names and do it here
        foreach ($enumcolnames as $name) {
            $columns[$name]['type'] = 'text';
            unset($columns[$name]['values']);
        }

        // now we check, if all index name are prepended with "<tablename_>" and prepend, if it is not
        $newindexes = array();
        $prep = $tablename . '_';
        $plen = strlen($prep);
        foreach ($indexes as $name => $index) {
            if (strncmp($prep, $name, $plen) == 0) {    // already prepended
                $newindexes[$name] = $index;
            } else {
                $index['name'] = $prep . $name;
                $newindexes[$prep . $name] = $index;
            }
        }
        $indexes = $newindexes;

        $f = array();
        $f['name'] = $tablename;

        $f['columns'] = $columns;
        $f['constraints'] = $constraints;
        $f['indexes'] = $indexes;

        return $f;
    }


    protected function createTableMigration(array $table): string
    {

        $query = 'CREATE TABLE ' . $table['name'] . " (\n";
        $ko = '';

        foreach ($table['columns'] as $vf) {
            $query .= $ko . "   " . $this->FieldMigration($vf);
            if (empty($ko))
                $ko = ",\n";
        }

        foreach ($table['constraints'] as $name => $con)
            $query .= $ko . '   CONSTRAINT ' . $name . ' ' . $this->ConstraintMigration($con, true);

        $query .= "\n);";

        return $query;
    }

    protected function createIndexMigration(array $table): array
    {
        $ret = array();

        foreach ($table['indexes'] as $name => $index)
            $ret[] = "CREATE INDEX " . $name . ' ON ' . $table['name'] . $this->IndexMigration($index) . ';';

        return $ret;
    }

    protected function FieldMigration(array $field, bool $withname = true, bool $withdefaultnull = true): string
    {
        // type indentifier conversion
        $type = match ($field['type']) {
            'integer' => 'int',
            'biginteger' => 'bigint',
            'decimal' => 'decimal',
            'varchar' => 'varchar',
            'date' => 'date',
            'enum' => 'enum',
            'text' => 'text',
            'datetime' => 'timestamp without time zone',
            'datetimetz' => 'timestamp with time zone',
            'json' => 'json',
            'blob' => 'bytea',
            'char' => 'char',
            'float' => 'real',
            'time' => 'time without time zone',
            'double' => 'double precision',
            default => ''
        };

        if (empty($type))
            throw new MigrationException('invalid field type:' . $field['type'] . ' in field:' . $field['name'], 1);

        if (isset($field['autoincrement']))
            $type = 'serial';       // special type short-cut for autoincrement fields

        $ret = '';

        if ($withname)
            $ret .= $field['name'];

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
            default:
        }

        // we ignore the $field['values'] because enums are already converted to a check-constraint

        if ($withdefaultnull) {
            if (!isset($field['nullable']))     // null value is not allowed
                $ret .= ' NOT NULL';
            else {
                $ret .= ' NULL';
            }

            if (isset($field['default'])) {
                if (strtolower($field['default']) == 'current_timestamp')
                    $ret .= " DEFAULT NOW()";
                else
                    $ret .= " DEFAULT '" . $field["default"] . "'";
            }
        }

        // Postgres has no on-update clause, so we have to ignore this property

        return $ret;
    }

    protected function FieldDefaultValueMigration(array $soll, $ist): string
    {

        // ALTER COLUMN column_name [SET DEFAULT value | DROP DEFAULT];

        if (isset($soll['default']) && ((strtolower($soll['default']) == 'current_timestamp') || (strtolower($soll['default']) == 'now()')))
            $soll['default'] = "now()";

        if (isset($ist['default']) && ((strtolower($ist['default']) == 'current_timestamp') || (strtolower($ist['default']) == 'now()')))
            $ist['default'] = "now()";

        // both fields are set and are identical -> no change
        if (isset($soll['default']) && isset($ist['default']) && ($soll['default'] == $ist['default']))
            return '';

        // both fields are not set -> no change
        if ((!isset($soll['default'])) && (!isset($ist['default'])))
            return '';

        if (!isset($soll['default']))
            return 'ALTER COLUMN ' . $soll['name'] . ' DROP DEFAULT';

        if ((strtolower($soll['default']) == 'current_timestamp') || (strtolower($soll['default']) == 'now()'))
            $d = "NOW()";
        elseif (str_contains($soll["default"], '::'))
            $d = $soll["default"];
        else
            $d = "'" . $soll["default"] . "'";

        return 'ALTER COLUMN ' . $soll['name'] . ' SET DEFAULT ' . $d;

    }

    protected function FieldNullValueMigration(array $soll, array $ist): string
    {

        // ALTER COLUMN column_name [SET NOT NULL| DROP NOT NULL];

        // both fields are set or both fields are not set
        if (isset($ist['nullable']) == isset($soll['nullable']))
            return '';

        if (isset($soll['nullable']))
            return 'ALTER COLUMN ' . $soll['name'] . ' DROP NOT NULL';
        else
            return 'ALTER COLUMN ' . $soll['name'] . ' SET NOT NULL';

    }

    protected function ConstraintMigration(array $con, bool $add = false): string
    {
        return $con['definition'];
    }

    protected function IndexMigration(array $idx, bool $add = false): string
    {

        $ret = '(';
        $ko = '';

        foreach ($idx['columns'] as $vf) {
            $ret .= $ko . $vf;
            if (empty($ko))
                $ko = ",";
        }

        $ret .= ')';

        return $ret;
    }


    protected function dropTableMigration(array $table): string
    {
        return ('DROP TABLE ' . $table['name'] . ";");
    }


    protected function alterTableMigration(array $soll, array $ist): array
    {

        $ret = array();
        $alterindex = array();

        // first we migrate indexes

        foreach ($ist['indexes'] as $vk => $vf) {
            if (!isset($soll['indexes'][$vk])) { // index not in $soll, we drop the index
                $alterindex[] = 'DROP INDEX ' . $vk;
            } else {
                // index in $soll and $ist, so we check if the sql-definition is changed
                $a = $this->IndexMigration($vf, true);
                $b = $this->IndexMigration($soll['indexes'][$vk], true);
                if (strcmp($a, $b) != 0) {  // something has changed
                    $alterindex[] = 'DROP INDEX ' . $vk;
                    $alterindex[] = "CREATE INDEX " . $vk . ' ON ' . $ist['name'] . $b;
                }
            }
        }

        // now we go through all indexes in $soll to check if they exist in $ist
        foreach ($soll['indexes'] as $vk => $vf) {
            if (!isset($ist['indexes'][$vk])) { // index is in $soll, but not in $ist, so we have to add it
                $alterindex[] = "CREATE INDEX " . $vk . ' ON ' . $ist['name'] . $this->IndexMigration($vf, true);
            }
        }

        // now we check the columns

        $dropfields = array();
        $alterfields = array();

        foreach ($ist['columns'] as $vk => $vf) {
            if (!isset($soll['columns'][$vk])) // column is not in $soll, we drop it
                $dropfields[] = 'DROP COLUMN ' . $vk;
            else {
                // column in $soll and $ist, so we check if the sql-definition is changed
                $a = $this->FieldMigration($vf);
                $b = $this->FieldMigration($soll['columns'][$vk]);
                if (strcmp($a, $b) != 0) {
                    $a = $this->FieldMigration($vf, false, false);
                    $b = $this->FieldMigration($soll['columns'][$vk], false, false);
                    if (strcmp($a, $b) != 0)
                        $alterfields[] = 'ALTER COLUMN ' . $vk . ' TYPE' . $b;
                    $b = $this->FieldDefaultValueMigration($soll['columns'][$vk], $vf);
                    if (!empty($b))
                        $alterfields[] = $b;
                    $b = $this->FieldNullValueMigration($soll['columns'][$vk], $vf);
                    if (!empty($b))
                        $alterfields[] = $b;
                }
            }
        }

        // now we go through all columns in $soll to check if they exist in $ist

        $insertfields = array();
        foreach ($soll['columns'] as $vk => $vf) {
            if (!isset($ist['columns'][$vk])) { // column is not in $ist, so we add it
                $a = 'ADD COLUMN ' . $this->FieldMigration($vf);
                $insertfields[] = $a;
            }
        }

        // now we check the constraints

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

        if (!empty($fields)) {
            $ko = '';
            $re = 'ALTER TABLE ' . $ist['name'] . "\n";
            foreach ($fields as $row) {
                $re .= $ko . '    ' . $row;
                $ko = ",\n";
            }
            $re .= ";";
            $ret[] = $re;
        }

        foreach ($alterindex as $row)
            $ret[] = $row . ';';

        return $ret;

    }

}
