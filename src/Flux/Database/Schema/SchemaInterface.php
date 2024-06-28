<?php
declare(strict_types=1);

namespace Flux\Database\Schema;

use Flux\Database\ConnectionPool;
use Flux\Database\DatabaseInterface;
use Flux\Logger\LoggerInterface;
use Flux\Config\Config;

interface SchemaInterface
{
    public function __construct(DatabaseInterface $db, LoggerInterface $logger, ConnectionPool $pool, Config $config);

    public function getSchema(): array;

    public function sortColumns(array $data): array;

    public function sortIndexes(array $data): array;

    public function sortSchema(array $data): array;

    public function getDumpFileName(bool $withmkdir = false): string;

    public function writeDump(): string;

    public function loadDump(string $filename = ''): array;

    public function getMigrationScript(string $filename = ''): ?array;

    public function toJSON(array $data): string;

    public function toString(array $data): string;

    public function createMigration(array $soll, array $ist): ?array;

}
