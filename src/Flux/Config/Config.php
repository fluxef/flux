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


    public function get(string $key, mixed $defaultvalue = null): mixed
    {
        if (empty($key))
            return null;

        if (isset($this->ConfVarArr[$key]))
            return ($this->ConfVarArr[$key]);
        else
            return $defaultvalue;

    }


    public function has(string $key): bool
    {
        if (empty($key))
            return false;

        return isset($this->ConfVarArr[$key]);

    }

    public function set(string $key = null, $value = null): self
    {

        if (!empty($key) && !is_null($value))
            $this->ConfVarArr[$key] = $value;

        return $this;

    }


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

    public function loadConfVarsFromDB(DatabaseInterface $db): self
    {
        $liste = $db->getlist('SELECT ckey,cvalue FROM ' . self::CONF_TABLE . ' WHERE active=?', array('yes'));

        foreach ($liste as $u)
            $this->setifnew($u['ckey'], $u['cvalue']);

        return $this;
    }


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
                mkdir($configpath, 0777,true);

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
