<?php
declare(strict_types=1);

namespace Flux\Core;

use Psr\Log\LogLevel;
use Flux\Container\Container;
use Flux\Events\EventDispatcher;
use Flux\Logger\Logger;
use Flux\Database\ConnectionPool;
use Flux\Config\Config;

class Core implements ApplicationInterface
{
    protected string $coreversion = '6.1.2';
    const   int ProductionEnvironmentState = 0;
    const   int StagingEnvironmentState = 1;
    const   int DevelopmentEnvironmentState = 2;

    /*
     * static class vars are only in the scope of the actual class, so we need "self" (NOT "static" !) here to use these
     * vars to always, even in child-classes, reference the static-vars of Flux\Core\Core
     *
     * if we need to use them in a child class, we have to use "parent::" in the child class !
     */

    protected static ?Core $instance = null;
    protected static ?Container $ContainerInstance = null;
    protected int $ApplicationEnvironmentState = self::ProductionEnvironmentState;
    protected string $basePath;

    public static function getApplication(): ApplicationInterface
    {
        return self::$instance;
    }

    public static function getContainer(): Container
    {
        return self::$ContainerInstance;
    }

    public static function setContainer(Container $instance): Container
    {
        self::$ContainerInstance = $instance;
        return $instance;
    }

    public static function get($id): mixed
    {
        return self::$ContainerInstance->get($id);
    }

    public static function has($id): bool
    {
        return self::$ContainerInstance->has($id);
    }

    public static function set($id, $callable)
    {
        self::$ContainerInstance->set($id, $callable);
    }

    public function __construct(string $rootPath)
    {
        self::$instance = $this;

        $this->setbasePath($rootPath);

        $this->registerApplicationEnvironmentState();

        $this->registerContainer();

        $this->registerCoreContainerServices();
        $this->registerApplicationContainerServices();     // can be extended in child class of application
        $this->registerExtraContainerServices();

        $this->registerBootstrapParams();

        $this->registerDatabases();

    }

    protected function registerContainer()
    {
        // DI-Services start here
        $di = new Container($GLOBALS);
        self::setContainer($di);

        // register myself;
        self::set('app', $this);

    }

    // dummy/empty function in this class. can be extended in child class from application for core application specific services
    protected function registerApplicationContainerServices()
    {

    }

    protected function registerExtraContainerServices()
    {
        /** @noinspection */
        $di = self::getContainer();   // DO NOT REMOVE THIS LINE! we need this as var in the include file!
        $servicesfile = $this->getbasePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'services.php';
        if (file_exists($servicesfile))
            require_once($servicesfile);
        unset($servicesfile);
    }

    protected function registerApplicationEnvironmentState()
    {

        if (!isset($_ENV['APP_ENV']))
            return;

        if ($_ENV['APP_ENV'] == 'stage') {
            $this->ApplicationEnvironmentState = static::StagingEnvironmentState;
            return;
        }

        if ($_ENV['APP_ENV'] == 'dev')
            $this->ApplicationEnvironmentState = static::DevelopmentEnvironmentState;

    }

    public function getApplicationEnvironmentState(): int
    {
        return $this->ApplicationEnvironmentState;
    }

    public function isProduction(): bool
    {
        return $this->ApplicationEnvironmentState == static::ProductionEnvironmentState;
    }

    public function isStaging(): bool
    {
        return $this->ApplicationEnvironmentState == static::StagingEnvironmentState;
    }

    public function isDevelopment(): bool
    {
        return $this->ApplicationEnvironmentState == static::DevelopmentEnvironmentState;
    }

    public function getVersion(bool $parent = false): string
    {
        return $this->coreversion;
    }

    public function setbasePath(string $basePath): ApplicationInterface
    {
        $this->basePath = rtrim($basePath, '\/');
        return $this;
    }

    public function getbasePath(): string
    {
        return $this->basePath;
    }


    protected function registerBootstrapParams()
    {

        $di = self::getContainer();

        $Config = $di->get('config');

        $base = $this->getBasePath();

        $Config->set('path.base', $base);
        $Config->set('path.config', $base . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
        $Config->set('path.framework.config', $base . DIRECTORY_SEPARATOR . 'inscms' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
        $Config->set('path.templates', $base . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR);

        $Config->set('path.var', $base . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR);
        $Config->set('path.cache', $base . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);

        $Config->set('path.storage', $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR);
        $Config->set('path.storage.image', $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR);
        $Config->set('path.storage.file', $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR);
        $Config->set('path.storage.video', $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'video' . DIRECTORY_SEPARATOR);

        // load Defaults from framework directory
        $Config->setConfDir($base . DIRECTORY_SEPARATOR . 'inscms' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
        $Config->loadConfFromFile('app.php');

        // load defaults from web-app directory
        $Config->setConfDir($base . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
        $Config->loadConfFromFile('app.php');
        if ($this->isDevelopment())
            $Config->loadConfFromFile('app.dev.php');

        // setup logger
        $logger = $di->get('logger');

        if ($Config->has('path.logger'))
            $logger->setLogPath($Config->get('path.logger'));

        if ($Config->has('path.logger.emergency'))
            $logger->setLogLevelPath(LogLevel::EMERGENCY, $Config->get('path.logger.emergency'));

        if ($Config->has('path.logger.alert'))
            $logger->setLogLevelPath(LogLevel::ALERT, $Config->get('path.logger.alert'));

        if ($Config->has('path.logger.critical'))
            $logger->setLogLevelPath(LogLevel::CRITICAL, $Config->get('path.logger.critical'));

        if ($Config->has('path.logger.error'))
            $logger->setLogLevelPath(LogLevel::ERROR, $Config->get('path.logger.error'));

        if ($Config->has('path.logger.warning'))
            $logger->setLogLevelPath(LogLevel::WARNING, $Config->get('path.logger.warning'));

        if ($Config->has('path.logger.notice'))
            $logger->setLogLevelPath(LogLevel::NOTICE, $Config->get('path.logger.notice'));

        if ($Config->has('path.logger.info'))
            $logger->setLogLevelPath(LogLevel::INFO, $Config->get('path.logger.info'));

        if ($Config->has('path.logger.debug'))
            $logger->setLogLevelPath(LogLevel::DEBUG, $Config->get('path.logger.debug'));


    }

    protected function registerDatabases()
    {

        $di = self::getContainer();

        $Config = $di->get('config');

        $conf = $Config->get('path.config') . 'database.json';

        $pool = $di->get(ConnectionPool::class);

        $pool->loadConfigFromFile($conf, $Config->get('path.storage'));

        $databases = $pool->getConfig();

        foreach ($databases as $conname => $dbconf) {

            if (!empty($dbconf['class'])) {
                $di->set($conname, function () use ($conname, $dbconf, $di) {
                    return new $dbconf['class'] (
                        $conname,
                        ConnectionPool: $di->get(ConnectionPool::class),
                        Logger: $di->get('logger'),
                        Drivername: $dbconf['driver'],
                        DSN: $dbconf['dsn'],
                        Params: $dbconf
                    );
                });

                if ($dbconf['lazyloading'] !== true) {
                    try {
                        $db = $di->get($conname);
                    } catch (Exception $e) {
                        $msg = 'FATAL DATABASE ERROR: ' . $e->getMessage();
                        if ($di->has('logger'))
                            $di->get('logger')->critical($msg);
                        else
                            error_log($msg);
                        exit(1);
                    }
                    unset($db);

                }
            }
        }

        // if base "db" database exists, try to get additional config from db sysparams
        // existing settings will not be overwritten
        if ($di->has('db'))
            $Config->loadConfVarsFromDB($di->get('db'));

    }

    protected function registerCoreContainerServices()
    {

        $di = self::getContainer();

        $di->set('event_dispatcher', function () use ($di) {
            return new EventDispatcher();
        });

        $di->set('logger', function () use ($di) {
            return new Logger();
        });

        $di->set('config', function () use ($di) {
            return new Config();
        });

        $di->set(ConnectionPool::class, function () use ($di) {
            return new ConnectionPool();
        });

    }

}
