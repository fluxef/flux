<?php
declare(strict_types=1);

namespace Flux\Database\Schema;

abstract class AbstractSchema
{
    abstract protected function fetchTables(): array;

    abstract protected function fetchColumns(string $tablename): array;

    abstract protected function fetchConstraints(string $tablename): array;

    abstract protected function fetchIndexes(string $tablename): array;

    abstract protected function normalizeTableStructure(string $tablename, array $columns, array $constraints, array $indexes): array;

    abstract protected function FieldMigration(array $field, bool $withname = true): string;

    abstract protected function ConstraintMigration(array $con, bool $add = false): string;

    abstract protected function IndexMigration(array $idx, bool $add = false): string;

    abstract protected function createTableMigration(array $table): string;

    abstract protected function createIndexMigration(array $table): array;

    abstract protected function dropTableMigration(array $table): string;

    abstract protected function alterTableMigration(array $soll, array $ist): array;

    public function toJSON(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function toString(array $data): string
    {
        $ret = '';
        foreach ($data as $s)
            $ret .= $s . "\n";
        return $ret;

    }


    public function getMigrationScript(string $filename = ''): ?array
    {
        $soll = $this->loadDump($filename);

        if (empty($soll)) {
            $this->logger->notice('dump is empty.');
            return null;
        }

        $ist = $this->getSchema();

        return $this->createMigration($soll, $ist);

    }

    public function getSchema(): array
    {

        $liste = $this->fetchTables();

        if (empty($liste)) {
            $this->logger->warning('database is empty.');
            return array();
        }

        $erg = array();

        foreach ($liste as $table) {

            $columns = $this->fetchColumns($table['name']);
            $constraints = $this->fetchConstraints($table['name']);
            $indexes = $this->fetchIndexes($table['name']);

            $erg[$table['name']] = $this->normalizeTableStructure($table['name'], $columns, $constraints, $indexes);

        }

        return $erg;
    }

    public function sortColumns(array $data): array
    {

        if (empty($data))
            return array();

        ksort($data, SORT_STRING);

        $temp = array();
        $erg = array();

        // first find autoincrement columns and put them first
        foreach ($data as $cname => $col) {
            if (isset($col['autoincrement']))
                $erg[$cname] = $col;
            else
                $temp[$cname] = $col;
        }

        // add the rest
        return array_merge($erg, $temp);


    }

    public function sortIndexes(array $data): array
    {

        if (empty($data))
            return array();

        ksort($data, SORT_STRING);

        $temp = array();
        $erg = array();

        // first find primary indexes and put them first
        foreach ($data as $iname => $index) {
            if (isset($index['primary']))
                $erg[$iname] = $index;
            else
                $temp[$iname] = $index;
        }

        // add the rest
        return array_merge($erg, $temp);

    }

    public function sortSchema(array $data): array
    {
        $erg = array();

        if (empty($data))
            return $erg;

        // sort tables
        ksort($data, SORT_STRING);

        foreach ($data as $tname => $table) {
            $table['columns'] = $this->sortColumns($table['columns']);
            $table['indexes'] = $this->sortIndexes($table['indexes']);

            $erg[$tname] = $table;
        }

        return $erg;
    }

    public function getDumpFileName(bool $withmkdir = false): string
    {
        $ConnectionName = $this->db->getConnectionName();
        $conf = $this->pool->getConfig($ConnectionName);
        $isinternal = isset($conf['internal']);

        if ($isinternal)
            $configpath = $this->config->get('path.base') . DIRECTORY_SEPARATOR . 'inscms' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        else
            $configpath = $this->config->get('path.config');

        $configpath .= 'schema';

        if ($withmkdir)
            if (!file_exists($configpath))
                mkdir($configpath, true);

        return $configpath . DIRECTORY_SEPARATOR . $ConnectionName . '.json';
    }

    public function writeDump(): string
    {
        $schema = $this->getSchema();
        $schema = $this->sortSchema($schema);

        $data = $this->toJSON($schema);

        $filename = $this->getDumpFileName(true);

        $file = fopen($filename, "w");
        fwrite($file, $data);
        fclose($file);

        return 'dump written to ' . $filename;

    }

    public function loadDump(string $filename = ''): array
    {
        if (empty($filename))
            $filename = $this->getDumpFileName();

        $content = file_get_contents($filename);

        if (empty($content))
            return array();

        $dump = json_decode($content, true);

        $erg = array();

        foreach ($dump as $tablename => $table) {
            if (empty($table['columns']))
                $columns = array();
            else
                $columns = $table['columns'];

            if (empty($table['constraints']))
                $constraints = array();
            else
                $constraints = $table['constraints'];

            if (empty($table['indexes']))
                $indexes = array();
            else
                $indexes = $table['indexes'];

            $erg[$tablename] = $this->normalizeTableStructure($tablename, $columns, $constraints, $indexes);

        }

        return $erg;
    }

    public function createMigration(array $soll, array $ist): ?array
    {

        $ret = array();

        // create tables that should exists but don't
        foreach ($soll as $key => $value) {
            if (!isset($ist[$key])) {
                $item = $this->createTableMigration($value);
                if (!empty($item))
                    $ret[] = $item;
                $items = $this->createIndexMigration($value);
                foreach ($items as $item)
                    $ret[] = $item;

            }
        }

        // go through all existing tables and alter-database if they have changed or delete them if neccessary
        foreach ($ist as $key => $value) {
            if (isset($soll[$key])) {
                $item = $this->alterTableMigration($soll[$key], $value);
                foreach ($item as $it)
                    $ret[] = $it;
            } else {
                $item = $this->dropTableMigration($value);
                $ret[] = $item;
            }
        }

        return $ret;
    }

}
