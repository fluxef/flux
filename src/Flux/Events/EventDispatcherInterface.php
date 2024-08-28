<?php
declare(strict_types=1);

namespace Flux\Events;

interface EventDispatcherInterface
{
    public function addListener(string $eventName, EventListenerInterface $listener, int $priority = 0): void;

    public function dispatch(EventInterface $event, string $eventName = null): EventInterface;

}
