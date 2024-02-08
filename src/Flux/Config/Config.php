<?php
declare(strict_types=1);

namespace Flux\Config;

use Flux\Database\DatabaseInterface;

class Config implements ConfigurationInterface
{
    protected string $confdir = '';
    protected array $ConfVarArr = array();

    const CONF_TABLE = 'configuration';

    public function __construct()
    {
    }

    public function setConfDir(string $confdir): self
    {
        $this->confdir = $confdir;
        return $this;
    }

    public function getConfDir(): string
    {
        return $this->confdir;
    }

    public function loadConfFromFile(string $file = ''): self
    {
        $params = $this->getConfFromFile($file);

        if (!empty($params))
            foreach ($params as $name => $value)
                $this->set($name, $value);

        return $this;

    }


    public function getConfFromFile(string $file = ''): array
    {
        if (empty($file))
            return array();

        $file = $this->confdir . $file;

        if (!file_exists($file))
            return array();

        $params = include($file);

        if (empty($params))
            return array();

        return $params;

    }

    /**
     * Liefert einen Parameter aus dem lokalen Storage mit seinem Typ (meist "string" bei DB-Parametern), oder null or defaultvalue
     *
     */
    public function get(string $key, mixed $defaultvalue = null): mixed
    {
        if (empty($key))
            return null;

        if (isset($this->ConfVarArr[$key]))
            return ($this->ConfVarArr[$key]);
        else
            return $defaultvalue;

    }

    /**
     * Prüft, ob ein Parameter im lokalen Storage ist
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (empty($key))
            return false;

        return isset($this->ConfVarArr[$key]);

    }

    /**
     * setzt einen Parameter im lokalen Storage, egal ob er existiert, oder nicht
     *
     * @param string|null $key
     * @param null $value
     * @return Config
     */
    public function set(string $key = null, $value = null): self
    {

        if (!empty($key) && !is_null($value))
            $this->ConfVarArr[$key] = $value;

        return $this;

    }

    /**
     * setzt einen Parameter im lokalen Storage, aber nur, wenn er noch nicht existiert
     *
     * @param string|null $key
     * @param null $value
     */
    public function setifnew(string $key = null, $value = null): self
    {
        if (empty($key))
            return $this;

        if (is_null($value))
            return $this;

        if ($this->has($key))
            return $this;

        $this->ConfVarArr[$key] = $value;

        return $this;

    }


    /**
     * Speichert eine Sysconf-Variable in der Datenbank und im Cache und Optional permanent in der DB
     *
     * @param DatabaseInterface $db
     * @param string|null $key
     * @param null $value
     * @return bool
     */
    public function saveConfVarinDB(DatabaseInterface $db, string $key = null, $value = null): bool
    {
        if (empty($key))
            return false;

        if (is_null($value))
            return false;

        $this->set($key, $value);

        $u = $db->get('SELECT configuration_id FROM ' . self::CONF_TABLE . ' WHERE ckey=?', array($key));

        if (empty($u))
            return false;

        $d = array();
        $d['cvalue'] = $value;
        $d['configuration_id'] = $u['configuration_id'];

        return $db->put(self::CONF_TABLE, $d, array('configuration_id'));
    }

    /**
     *  Lädt alle Variablen aus der DB in den Cache wenn es sie noch nicht gibt, Autoload wird ignoriert seit 20190426
     * @param DatabaseInterface $db
     */
    public function loadConfVarsFromDB(DatabaseInterface $db): self
    {
        $liste = $db->getlist('SELECT ckey,cvalue FROM ' . self::CONF_TABLE . ' WHERE active=?', array('yes'));

        foreach ($liste as $u)
            $this->setifnew($u['ckey'], $u['cvalue']);

        return $this;
    }

    /**
     * @return array
     */
    public function dumpStorage(): array
    {
        return $this->ConfVarArr;
    }


    public function getConfigFilePath(string $filename, ?string $subdir = null, bool $makedir = false, bool $isinternal = false): string
    {

        // just in case
        if ($isinternal)
            $makedir = false;

        if ($isinternal)
            $configpath = $this->ConfVarArr['path.base'] . DIRECTORY_SEPARATOR . 'inscms' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        else
            $configpath = $this->ConfVarArr['path.config'];

        $configpath .= $subdir;

        if ($makedir)
            if (!file_exists($configpath))
                mkdir($configpath, true);

        return $configpath . DIRECTORY_SEPARATOR . $filename . '.json';
    }


    public function ConfigFileExists(string $filename, ?string $subdir = null, bool $isinternal = false): bool
    {

        if ($isinternal)
            $configpath = $this->ConfVarArr['path.base'] . DIRECTORY_SEPARATOR . 'inscms' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        else
            $configpath = $this->ConfVarArr['path.config'];

        $configpath .= $subdir . DIRECTORY_SEPARATOR . $filename . '.json';

        return file_exists($configpath);

    }

    public function loadConfig(string $filename = ''): array
    {
        if (empty($filename))
            return array();

        $filepath = $this->getConfigFilePath($filename);

        $content = file_get_contents($filepath);

        if (empty($content))
            return array();

        return json_decode($content, true);
    }

}
