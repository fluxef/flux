<?php
declare(strict_types=1);

namespace Flux\Logger;

use Psr\Log\LoggerInterface as PsrLoggerInterface;


/**
 * Interface LoggerInterface
 * @package Flux\Logger
 */
interface LoggerInterface extends PsrLoggerInterface
{

    public function __construct(string $app = 'ins-cmf', $facility = LOG_USER);

    public function setLogPath(string $path);

    public function setLogLevelPath(string $level, string $path);

    public function setUserID(int $uid = 0): self;

    public function disableBacktrace();

    public function getClientIP(): string;

    public function setClientIP(string $ip): self;

    public function setHost(string $host): self;

    public function setLogOptions(?int $options = null): self;

}
