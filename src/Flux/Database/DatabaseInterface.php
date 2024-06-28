<?php
declare(strict_types=1);

namespace Flux\Database;

use Flux\Database\Query\Builder;
use Flux\Logger\LoggerInterface;
use \PDO;


interface DatabaseInterface
{


    public function __construct(string           $ConnectionName,
                                ?ConnectionPool  $ConnectionPool = null,
                                ?LoggerInterface $Logger = null,
                                ?string          $DSN = null,
                                ?string          $Drivername = null,
                                ?array           $Params = null);

    public function connection(string $Name): ?DatabaseInterface;

    public function getDBName(): string;

    public function getHostName(): string;

    public function getDriverName(): string;

    public function getConnectionName(): string;

    public function getDriverAttribute($attribute): mixed;

    public function table(string $table): Builder;

    public function add(string $table, array $data, string $SerialColumn = null): int;

    /**
     * Lese einen Datensatz in ein Array, dabei ist der Feldname der Index und der
     * Feldinhalt der Wert.
     *
     * @param string $sql
     * @param array $binding
     * @return array
     */
    public function get(string $sql, array $binding = array()): array;

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
    public function put(string $table, array $data, array $keynames, bool $changelog = true, array $ignorekeynames = null, DatabaseInterface $changelogdb = null): bool;

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
    public function getlistUI(string $sql, array $binding = array(), string $indexname = '', string $valuename = '', string $value = 'value', string $text = 'text'): array;

    /**
     * liefert eine liste datensätze, auch assoziativ nach einem feld indiziert und auch statt des records eine variable sein können
     *
     * @param string $sql
     * @param array|null $binding
     * @param string|null $indexname
     * @param string|null $valuename
     * @return array
     */
    public function getlist(string $sql, array $binding = NULL, string $indexname = null, string $valuename = null): array;

    /**
     * löschen daten in tabelle $table, in $data stehen die keys
     *
     * @param string $table
     * @param array $data
     * @return bool
     */
    public function del($table = '', $data = array()): bool;

    /**
     * setzt und löscht das debug-flag
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug = true): void;

    /**
     * liefert den status des debug-flags
     *
     * @return bool
     */
    public function getDebug(): bool;


    /*
     * liefert das PDO Datenbankobjekt um direkt darauf zuzugreifen
     *
     */
    /**
     * @return PDO
     */
    public function getPDO(): PDO;

    /**
     * erzeugt und liefert einen DatumZeit-String der in mysql timestamp und datetime feldern genutzt werden kann
     * wenn kein parameter übergeben wird, wird der aktuelle zeitpunkt geliefert
     *
     * @param string $time
     * @param string $timeformat
     * @return string
     */
    public function timestamp(string $time = 'now', string $timeformat = '', bool $withTZ = false): string;

    /**
     * führt ein select-query durch
     *
     * @param string $sql
     * @param array|null $binding
     * @return array
     */
    public function select(string $sql, array $binding = null): array;

    /**
     * führt ein update-query durch
     *
     * @param string $sql
     * @param array|null $binding
     * @return bool
     */
    public function update(string $sql, array $binding = null): bool;

    /**
     * führt ein insert-query durch
     *
     * @param string $sql
     * @param array|null $binding
     * @return int
     */
    public function insert(string $sql, array $binding = null, string $SerialColumn = null): int;

    /**
     * führt ein delete query durch
     *
     * @param string $sql
     * @param array|null $binding
     * @return bool
     */
    public function delete(string $sql, array $binding = null): bool;

    /**
     * führt ein allgemeines query durch welches weder parameter hat noch einen rückgabewert liefert
     *
     * @param string $sql
     * @return bool
     */
    public function statement(string $sql): bool;

}


