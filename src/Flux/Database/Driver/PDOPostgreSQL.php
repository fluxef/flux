<?php
declare(strict_types=1);

namespace Flux\Database\Driver;

use PDO;
use PDOException;

use Flux\Database\DatabaseInterface;
use Flux\Database\ConnectionPool;
use Flux\Logger\LoggerInterface;

class PDOPostgreSQL extends PDOAbstract implements DatabaseInterface
{
    protected string $QuoteCharIdentifier = '"';
    protected string $QuoteCharString = "'";

    public function __construct(string           $ConnectionName,
                                ?ConnectionPool  $ConnectionPool = null,
                                ?LoggerInterface $Logger = null,
                                ?string          $DSN = null,
                                ?string          $Drivername = null,
                                ?array           $Params = null)
    {

        $this->ConnectionName = $ConnectionName;

        if (!is_null($Logger))
            $this->Logger = $Logger;

        if (!is_null($ConnectionPool)) {
            $this->ConnectionPool = $ConnectionPool;
            $this->ConnectionPool->set($ConnectionName, $this);
        }

        if (!is_null($Params['debug']))
            $this->setDebug($Params['debug']);

        if (empty($Drivername))
            $this->Drivername = 'pgsql';
        else
            $this->Drivername = $Drivername;

        if (!empty($Params['database']))
            $this->Databasename = $Params['database'];

        if (isset($Params['host']))
            $this->Hostname = $Params['host'];

        if (empty($DSN)) {

            $DSN = $this->Drivername . ':host=' . $Params['host'];

            if (!empty($Params['port']) && ($Params['port'] != 5432))
                $DSN .= ';port=' . $Params['port'];

            $DSN .= ';dbname=' . $Params['database'];

        }

        if (empty($Params['options']) || (!is_array($Params['options'])))
            $Options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
        else
            $Options = $Params['options'];

        $this->Driver = new PDO($DSN, $Params['user'], $Params['password'], $Options);

    }


    /**
     * inserts one row in of an associative arry (keyname is columnname) in db
     */
    public function add(string $table, array $data, string $SerialColumn = null): int
    {
        if (empty($data)) {
            if (!is_null($this->Logger))
                $this->Logger->error('data is empty');
            return 0;
        }

        if (empty($table)) {
            if (!is_null($this->Logger))
                $this->Logger->error('tablename is empty');
            return 0;
        }

        $sql = "INSERT INTO " . $table . " (";
        $sqld = " VALUES (";
        $ko = '';
        foreach ($data as $feld => $inhalt) {
            $sql .= $ko . $this->QuoteCharIdentifier . $feld . $this->QuoteCharIdentifier;
            $sqld .= $ko . ':' . $feld;
            $ko = ',';
        }

        $sql .= ') ' . $sqld . ')';

        if (!is_null($SerialColumn))
            $sql .= ' returning ' . $SerialColumn;

        return $this->insert($sql, $data, $SerialColumn);
    }

    public function insert(string $sql, array $binding = null, string $SerialColumn = null): int
    {
        if (empty($sql)) {
            if (!is_null($this->Logger))
                $this->Logger->error('sql statement is empty.');
            return 0;
        }

        $time_start = microtime(true);

        try {
            $statement = $this->Driver->prepare($sql);
            $statement->execute($binding);  // return value of statement

            if (is_null(($SerialColumn))) {   // we do not have a "returning id" have to get the insert id with the second best way, with lastinsertid
                $id = (int)$this->Driver->lastInsertId();
            } else {
                $returning = $statement->fetchAll();
                $id = (int)$returning[0][$SerialColumn];
            }

        } catch (PDOException $ex) {

            if (!is_null($this->Logger)) {
                $logarr = array(
                    'msgid' => 'sqlerror',
                    'ip' => $this->Logger->getClientIP(),
                    'database' => $this->Databasename,
                    'sql' => $sql
                );
                $logarr['error'] = $ex->getCode();
                $this->Logger->critical($ex->getMessage(), $logarr);
            }
            return 0;
        }

        if ($this->Debug) {
            $time_end = microtime(true);
            $time_diff = ($time_end - $time_start) * 1000; // sekunden mal 1000 = millisekunden
            if (!is_null($this->Logger))
                $this->Logger->debug('DB: ' . $this->Databasename . ' Time: ' . number_format($time_diff, 4) . ' ms  Query: ' . $sql);
        }

        return $id;
    }

}

