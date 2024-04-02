<?php
declare(strict_types=1);

namespace Flux\Container;

/**
 * Class Container
 * @package Flux\Container
 */
class Container implements ContainerInterface

{

    /**
     * Ein Array von callables/Closures, die Objekt-Instanzen erzeugen
     */
    protected array $callables = array();

    /**
     * Ein Array von Objekt-Instanzen, falls die objekte angelegt wurden
     *
     */
    protected array $instances = array();


    public function __construct(protected array $variables = array())
    {
    }

    public function getVar(string $key): mixed
    {
        return $this->variables[$key];
    }

    public function setVar(string $key, $val)
    {
        $this->variables[$key] = $val;
    }

    public function hasVar(string $key): bool
    {
        return isset($this->variables[$key]);
    }

    public function unsetVar(string $key)
    {
        unset($this->variables[$key]);
    }

    public function setInstance($id, $obj)
    {
        $this->instances[$id] = $obj;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     */

    public function get(mixed $id): mixed
    {
        $id = ltrim($id, '\\');

        if (!isset($this->instances[$id]))
            $this->instances[$id] = $this->newInstance($id);

        return $this->instances[$id];
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(mixed $id): bool
    {
        $id = ltrim($id, '\\');

        if (isset($this->instances[$id]))
            return true;

        return isset($this->callables[$id]);
    }

    /**
     * Erzeugt immer eine neue Instanz
     *
     * @param $id
     * @return mixed
     * @throws NotFoundException
     */
    public function newInstance($id): object
    {
        $id = ltrim($id, '\\');

        if (!$this->has($id))
            throw new NotFoundException($id . ' not found');

        return call_user_func($this->callables[$id], $this);
    }

    /**
     * @param $id
     * @param $callable
     */
    public function set($id, $callable)
    {
        $id = ltrim($id, '\\');
        $this->callables[$id] = $callable;
        unset($this->instances[$id]);
    }

}
