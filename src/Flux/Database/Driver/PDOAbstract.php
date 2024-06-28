<?php
declare(strict_types=1);

namespace Flux\Database\Driver;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use PDO;
use PDOException;

use Flux\Database\DatabaseInterface;
use Flux\Database\ConnectionPool;
use Flux\Logger\LoggerInterface;
use Flux\Database\Query\Builder;

abstract class PDOAbstract implements DatabaseInterface
{
    protected ?ConnectionPool $ConnectionPool = null;
    protected ?LoggerInterface $Logger = null;
    protected PDO $Driver;

    protected bool $Debug = false;

    protected string $QuoteCharIdentifier = '`';
    protected string $QuoteCharString = '`';

    protected string $ConnectionName;
    protected string $Drivername = '';
    protected string $Databasename = '';
    protected string $Hostname = '';

    protected string $ChangelogTable = 'changes';   // TODO add setter for this property

    abstract public function __construct(string           $ConnectionName,
                                         ?ConnectionPool  $ConnectionPool = null,
                                         ?LoggerInterface $Logger = null,
                                         ?string          $DSN = null,
                                         ?string          $Drivername = null,
                                         ?array           $Params = null);

    abstract public function add(string $table, array $data, string $SerialColumn = null): int;

    abstract public function insert(string $sql, array $binding = null, string $SerialColumn = null): int;

    public function connection(string $Name): ?DatabaseInterface
    {
        if (isset($this->ConnectionPool))
            return $this->ConnectionPool->get($Name);
        else
            return null;
    }

    public function getDBName(): string
    {
        return $this->Databasename;
    }

    public function getDriverName(): string
    {
        return $this->Drivername;
    }

    public function getHostName(): string
    {
        return $this->Hostname;
    }

    public function getConnectionName(): string
    {
        return $this->ConnectionName;
    }

    public function getDriverAttribute($attribute): mixed
    {
        if (is_null($this->Driver))
            return null;
        else
            return $this->Driver->getAttribute($attribute);
    }

    public function table(string $table): Builder
    {
        $builder = Builder::create($this, $this->Logger);
        $builder->table($table);
        return $builder;
    }

    /**
     * Lese einen Datensatz in ein Array, dabei ist der Feldname der Index und der
     * Feldinhalt der Wert. Unterschiedliche Parameter, je nachdem ob $var1 ein array (Bindings) oder ein String (indexname)ist.
     * Variante ALT:   get($sql,$indexname)
     * Variante NEU:   get($sql,$binding,$indexname)
     *
     * @param string $sql
     * @param array $binding
     * @return array
     */
    public function get(string $sql, array $binding = array()): array
    {
        $sql .= ' LIMIT 1';
        $data = $this->select($sql, $binding);

        if (isset($data[0]))
            return $data[0];
        else
            return $data;
    }

    /**
     * Speichert einen Datensatz in die Datenbank
     *
     * @param string $table
     *            Name der Datenbanktabellen
     * @param array $data
     *            Assoziatives Array der zu speicherneden Daten
     * @param array $keynames
     *            Array der Schlüssel die den Gesamtkey / kombinierten Index bilden
     * @param bool $changelog
     *            true = änderungen in changelog-db vermerken
     * @param array|null $ignorekeynames
     *            Array der Schlüssel die zur Veränderungsprüfung nicht verwendet werden sollen (z.B. last change timestamp o.ä.)
     * @param DatabaseInterface|null $changelogdb
     *            $db objekt, falls der schangelog in eine andere DB geschrieben werden soll
     * @return bool true=hat geklappt, false=hat nicht geklappt
     */
    public function put(string $table, array $data, array $keynames, bool $changelog = true, array $ignorekeynames = null, DatabaseInterface $changelogdb = null): bool

    {
        global $userobj;        // TODO eliminate bad global dependency

        $userid = 0;
        if (isset($userobj))
            if (isset($userobj['user_id']))
                $userid = $userobj['user_id'];

        if (empty($table)) {
            if (!is_null($this->Logger))
                $this->Logger->error('tablename is empty');
            return false;
        }

        if (empty($data)) {
            if (!is_null($this->Logger))
                $this->Logger->error('data is empty');
            return false;
        }

        if (empty($keynames)) {
            if (!is_null($this->Logger))
                $this->Logger->error('keynames is empty');
            return false;
        }

        $where = '';
        $and = '';

        $pu = '';
        $keystring = '';

        $keydata = array();
        foreach ($keynames as $feld) {
            $where .= $and . $this->QuoteCharIdentifier . $feld . $this->QuoteCharIdentifier . '=:' . $feld;
            $and = ' AND ';

            $keystring .= $pu . $data[$feld];
            $pu = '.';

            $keydata[$feld] = $data[$feld];
        }

        $select = '';
        $ko = '';

        foreach ($data as $feld => $inhalt) {
            $select .= $ko . $this->QuoteCharIdentifier . $feld . $this->QuoteCharIdentifier;
            $ko = ',';
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $this->QuoteCharIdentifier . $table . $this->QuoteCharIdentifier . ' WHERE ' . $where;

        $alt = $this->get($sql, $keydata);

        if (empty($alt)) {
            // kann auch OK sein (z.B. weil aus put+add aufgerufen) daher kein logging. $this->logger->error('no data found. sql=' . $sql);
            return false;
        }

        $neu = array();
        foreach ($data as $feld => $inhalt) {
            if ($inhalt != $alt[$feld])
                $neu[$feld] = $inhalt;
        }

        // remove $keynames from indexnamen because keys are generated automaticly, mostly and are not "normal" data
        foreach ($keynames as $feld)
            unset($neu[$feld]);

        // remove $ignorekeynames from $neu because their changes are not tracked/checked if there are changes
        if (!empty($ignorekeynames))
            foreach ($ignorekeynames as $feld)
                unset($neu[$feld]);

        if (empty($neu)) // no change, so nothing to write
            return true;

        // now we do have changes

        // so, we add the $ignorekeynames again, to have all data
        if (!empty($ignorekeynames))
            foreach ($ignorekeynames as $feld)
                $neu[$feld] = $data[$feld];

        // index felder in neuen datensatz rüberkopieren
        $neuwithkey = $neu;

        foreach ($keynames as $feld)
            $neuwithkey[$feld] = $data[$feld];

        $sql = 'UPDATE ' . $table . ' SET ';

        $ko = '';
        foreach ($neu as $feld => $inhalt) {
            $sql .= $ko . $this->QuoteCharIdentifier . $feld . $this->QuoteCharIdentifier . '=:' . $feld;
            $ko = ',';
        }

        $sql .= ' WHERE ' . $where;

        if (!$this->update($sql, $neuwithkey)) {
            if (!is_null($this->Logger))
                $this->Logger->error('update failed. sql=' . $sql);
            return false;
        }

        if (!$changelog)
            return true;

        // remove $keynames from $neu because their changes are not written in $changelog
        foreach ($keynames as $feld)
            unset($neu[$feld]);

        // remove $ignorekeynames from $neu because their changes are not written in $changelogdb
        if (!empty($ignorekeynames))
            foreach ($ignorekeynames as $feld)
                unset($neu[$feld]);

        $extdb = is_object($changelogdb);

        $log = array();
        $log['objid'] = $keystring;
        $log['userid'] = $userid;
        $log['created'] = $this->timestamp();
        $log['dbtable'] = $table;

        foreach ($neu as $feld => $inhalt) {
            $log['dbfieldname'] = $feld;
            $log['oldval'] = $alt[$feld];
            $log['newval'] = $inhalt;

            if ($extdb)
                $changelogdb->add($this->ChangelogTable, $log);
            else
                $this->add($this->ChangelogTable, $log);
        }

        return true;
    }

    /**
     * erzeugt eine Liste mit Value/Text records, wie sie für GUI-Dbforms DropDownfelder genutzt wird
     *
     * @param string $sql
     * @param array $binding
     * @param string $indexname
     * @param string $valuename
     * @param string $value
     * @param string $text
     * @return array
     */
    public function getlistUI(string $sql, array $binding = array(), string $indexname = '', string $valuename = '', string $value = 'value', string $text = 'text'): array
    {

        if (empty($indexname)) {
            if (!is_null($this->Logger))
                $this->Logger->error('indexname is empty');
            return array();
        }

        if (empty($valuename)) {
            if (!is_null($this->Logger))
                $this->Logger->error('valuename is empty');
            return array();
        }

        $liste = $this->getlist($sql, $binding);

        if (empty($liste))
            return $liste;

        $ret = array();

        foreach ($liste as $row) {
            $data = array();
            $data[$value] = $row[$indexname];
            $data[$text] = $row[$valuename];
            $ret[] = $data;
        }

        return $ret;
    }

    /**
     * liefert eine liste datensätze, auch assoziativ nach einem feld indiziert und auch statt des records eine variable sein können
     *
     * @param string $sql
     * @param array|null $binding
     * @param string|null $indexname
     * @param string|null $valuename
     * @return array
     */
    public function getlist(string $sql, array $binding = null, string $indexname = null, string $valuename = null): array

    {
        $liste = $this->select($sql, $binding);

        if (empty($liste))
            return $liste;

        if (empty($indexname) && empty($valuename))
            return $liste;

        $ret = array();

        $withindex = !empty($indexname);
        $allfields = empty($valuename);

        foreach ($liste as $row) {
            if ($allfields)
                $data = $row;
            else
                $data = $row[$valuename];

            if ($withindex)
                $ret[$row[$indexname]] = $data;
            else
                $ret[] = $data;
        }

        return $ret;
    }

    /**
     * löschen daten in tabelle $table, in $data stehen die keys
     *
     * @param string $table
     * @param array $data
     * @return bool
     */
    public function del($table = '', $data = array()): bool
    {
        if (empty($data)) {
            if (!is_null($this->Logger))
                $this->Logger->error('data is empty');
            return false;
        }

        if (empty($table)) {
            if (!is_null($this->Logger))
                $this->Logger->error('tablename is empty');
            return false;
        }

        $sql = 'DELETE FROM ' . $table . ' WHERE ';

        $ko = '';

        foreach ($data as $feld => $inhalt) {
            $sql .= $ko . $this->QuoteCharIdentifier . $feld . $this->QuoteCharIdentifier . '=:' . $feld;
            $ko = ' AND ';
        }

        return $this->delete($sql, $data);
    }

    /**
     * setzt und löscht das debug-flag
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug = true): void
    {
        if ($debug == true)
            $this->Debug = true;
        else
            $this->Debug = false;
    }

    /**
     * liefert den status des debug-flags
     *
     * @return bool
     */
    public function getDebug(): bool
    {
        return $this->Debug;
    }


    /*
    * Funktionen, die auf PDO aufsetzen
    *
    */

    /*
     * liefert das PDO Datenbankobjekt um direkt darauf zuzugreifen
     *
     */
    public function getPDO(): PDO
    {
        return $this->Driver;
    }

    /**
     * erzeugt und liefert einen DatumZeit-String der in mysql timestamp und datetime feldern genutzt werden kann
     * wenn kein parameter übergeben wird, wird der aktuelle zeitpunkt geliefert
     *
     */
    public function timestamp(string $timestamp = 'now', string $timestampformat = '', bool $withTZ = false): string
    {
        $tz = new DateTimeZone(date_default_timezone_get());

        try {
            if (empty($timeformat))
                $dt = new DateTime($timestamp, $tz);
            else
                $dt = DateTime::createFromFormat($timestampformat, $timestamp, $tz);
        } catch (Exception $e) {

            if (!is_null($this->Logger)) {
                $logarr = array(
                    'msgid' => 'sqlerror',
                    'ip' => $this->Logger->getClientIP(),
                    'database' => $this->Databasename,
                    'time' => $timestamp
                );
                $logarr['error'] = $e->getCode();
                $this->Logger->critical($e->getMessage(), $logarr);
            }

            return '';
        }

        if ($withTZ)
            return $dt->format(DateTimeInterface::RFC3339);
        else
            return $dt->format('Y-m-d H:i:s');

    }

    public function select(string $sql, array $binding = null): array
    {
        if (empty($sql)) {
            if (!is_null($this->Logger))
                $this->Logger->error('sql statement is empty.');
            return array();
        }

        $time_start = microtime(true);

        try {
            $statement = $this->Driver->prepare($sql);
            $statement->execute($binding);

            if ($this->Debug) {
                $time_end = microtime(true);
                $time_diff = ($time_end - $time_start) * 1000; // sekunden mal 1000 = millisekunden
                if (!is_null($this->Logger))
                    $this->Logger->debug('DB: ' . $this->Databasename . ' Time: ' . number_format($time_diff, 4) . ' ms  Query: ' . $sql);
            }

            return $statement->fetchAll(PDO::FETCH_ASSOC);
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

            return array();
        }
    }

    public function update(string $sql, array $binding = null): bool
    {
        if (empty($sql)) {
            if (!is_null($this->Logger))
                $this->Logger->error('sql statement is empty.');
            return false;
        }

        $time_start = microtime(true);

        try {
            $this->Driver->prepare($sql)->execute($binding);
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

            return false;
        }

        if ($this->Debug) {
            $time_end = microtime(true);
            $time_diff = ($time_end - $time_start) * 1000; // sekunden mal 1000 = millisekunden
            if (!is_null($this->Logger))
                $this->Logger->debug('DB: ' . $this->Databasename . ' Time: ' . number_format($time_diff, 4) . ' ms  Query: ' . $sql);
        }

        return true;
    }

    /**
     * führt ein delete query durch
     *
     * @param string $sql
     * @param array|null $binding
     * @return bool
     */
    public function delete(string $sql, array $binding = null): bool
    {
        if (empty($sql)) {
            if (!is_null($this->Logger))
                $this->Logger->error('sql statement is empty.');
            return false;
        }

        $time_start = microtime(true);
        try {
            $this->Driver->prepare($sql)->execute($binding);
        } catch (PDOException $ex) {

            if (!is_null($this->Logger)) {

                $logarr = array(
                    'msgid' => 'sqlerror',
                    'ip' => $this->Logger->getClientIP(),
                    'database' => $this->Databasename,
                    'sql' => $sql
                );

                $logarr['error'] = $ex->getCode();
                $this->Logger->critical($error = $ex->getMessage(), $logarr);
            }
            return false;
        }

        if ($this->Debug) {
            $time_end = microtime(true);
            $time_diff = ($time_end - $time_start) * 1000; // sekunden mal 1000 = millisekunden
            if (!is_null($this->Logger))
                $this->Logger->debug('DB: ' . $this->Databasename . ' Time: ' . number_format($time_diff, 4) . ' ms  Query: ' . $sql);
        }

        return true;
    }

    /**
     * führt ein allgemeines query durch welches weder parameter hat noch einen rückgabewert liefert
     *
     * @param string $sql
     * @return bool
     */
    public function statement(string $sql): bool
    {
        if (empty($sql)) {
            if (!is_null($this->Logger))
                $this->Logger->error('sql statement is empty.');
            return false;
        }

        $time_start = microtime(true);

        try {
            $this->Driver->query($sql)->closeCursor();
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

            return false;
        }

        if ($this->Debug) {
            $time_end = microtime(true);
            $time_diff = ($time_end - $time_start) * 1000; // sekunden mal 1000 = millisekunden
            if (!is_null($this->Logger))
                $this->Logger->debug('DB: ' . $this->Databasename . ' Time: ' . number_format($time_diff, 4) . ' ms  Query: ' . $sql);
        }

        return true;
    }
}



