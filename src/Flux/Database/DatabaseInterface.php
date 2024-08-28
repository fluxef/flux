<?php
declare(strict_types=1);

namespace Flux\Database;

use Flux\Database\Query\Builder;
use Flux\Logger\LoggerInterface;
use PDO;


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
     * Read a record into an array, where the field name is the index and the field content is the value.
     */
    public function get(string $sql, array $binding = array()): array;

    /**
     * Saves a record to the database
     *
     * @param string $table
     *            Name of the database tables
     * @param array $data
     *            Associative array of data to be stored
     * @param array $keynames
     *            Array of keys that form the total key / combined index
     * @param bool $changelog
     *            true = Record changes in changelog-db
     * @param array|null $ignorekeynames
     *            Array of keys that should not be used for change checking (e.g. last change timestamp or similar)
     * @param DatabaseInterface|null $changelogdb
     *            $db object if the schangelog should be written to another DB
     * @return bool true=worked, false=did not work
     */
    public function put(string $table, array $data, array $keynames, bool $changelog = true, array $ignorekeynames = null, DatabaseInterface $changelogdb = null): bool;

    /**
     * creates a list of value/text records as used for GUI-Dbforms dropdown fields
     */
    public function getlistUI(string $sql, array $binding = array(), string $indexname = '', string $valuename = '', string $value = 'value', string $text = 'text'): array;

    /**
     * returns a list of records, also associatively indexed by a field and can also be a variable instead of the record
     */
    public function getlist(string $sql, array $binding = NULL, string $indexname = null, string $valuename = null): array;

    /**
     * delete data in table $table, in $data are the keys
     */
    public function del($table = '', $data = array()): bool;

    /**
     * sets and clears the debug flag
     */
    public function setDebug(bool $debug = true): void;

    /**
     * returns the status of the debug flag
     */
    public function getDebug(): bool;


    /*
     * provides the PDO database object to access it directly as a last resort
     */
    public function getPDO(): PDO;

    /**
     * creates and returns a datetime string that can be used in mysql timestamp and datetime fields
     * if no parameter is passed, the current time is returned
     */
    public function timestamp(string $time = 'now', string $timeformat = '', bool $withTZ = false): string;

    /**
     * executes a select query
     */
    public function select(string $sql, array $binding = null): array;

    /**
     * executes an update query
     */
    public function update(string $sql, array $binding = null): bool;

    /**
     * executes an insert query
     */
    public function insert(string $sql, array $binding = null, string $SerialColumn = null): int;

    /**
     * executes a delete query
     */
    public function delete(string $sql, array $binding = null): bool;

    /**
     * executes a general query which has neither parameters nor a return value
     */
    public function statement(string $sql): bool;

}


