<?php
declare(strict_types=1);

namespace Flux\Config;

use Flux\Database\DatabaseInterface;

interface ConfigurationInterface
{

    /**
     * Returns a parameter from the local storage with its type (usually "string" for DB parameters), or null or defaultvalue
     */
    public function get(string $key, mixed $defaultvalue = null): mixed;

    /**
     * Checks whether a parameter is in local storage
     */
    public function has(string $key): bool;

    /**
     * Sets a parameter in the local storage, regardless of whether it exists or not
     */
    public function set(string $key = null, $value = null): self;

    /**
     * Sets a parameter in the local storage, but only if it does not already exist
     *
     */
    public function setifnew(string $key = null, $value = null): self;

    public function getConfigFilePath(string $filename, ?string $subdir, bool $makedir = false, bool $isinternal = false): string;

    public function ConfigFileExists(string $filename, ?string $subdir = null, bool $isinternal = false): bool;

    public function loadConfig(string $filename = ''): array;

    public function dumpStorage(): array;

    /**
     * Stores a Sysconf variable in the database and cache and optionally permanently in the DB
     */
    public function saveConfVarinDB(DatabaseInterface $db, string $key = null, $value = null): bool;

    /**
     *  Loads all variables from the DB into the cache if they do not exist yet, autoload is ignored since 20190426
     */
    public function loadConfVarsFromDB(DatabaseInterface $db): self;

}
