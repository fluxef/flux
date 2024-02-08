<?php
declare(strict_types=1);

namespace Flux\Config;

interface ConfigurationInterface
{
    public function getConfigFilePath(string $filename, ?string $subdir, bool $makedir = false, bool $isinternal = false): string;

    public function ConfigFileExists(string $filename, ?string $subdir = null, bool $isinternal = false): bool;

    public function loadConfig(string $filename = ''): array;

    public function get(string $key, mixed $defaultvalue = null): mixed;

    public function has(string $key): bool;

    public function set(string $key = null, $value = null): self;

    public function setifnew(string $key = null, $value = null): self;


}
