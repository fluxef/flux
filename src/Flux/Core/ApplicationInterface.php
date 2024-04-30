<?php
declare(strict_types=1);

namespace Flux\Core;

use Flux\Container\Container;

interface ApplicationInterface
{
    public static function getApplication(): ApplicationInterface;

    public static function getContainer(): Container;

    public static function setContainer(Container $instance): Container;

    public static function get($id): mixed;

    public static function has($id): bool;

    public static function set($id, $callable);

    public function getApplicationEnvironmentState(): int;

    public function isProduction(): bool;

    public function isStaging(): bool;

    public function isDevelopment(): bool;

    public function getVersion(bool $all = false): string;

    public function setbasePath(string $basePath): ApplicationInterface;

    public function getbasePath(): string;

}
