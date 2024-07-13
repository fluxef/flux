<?php
declare(strict_types=1);

namespace Flux\Events;

class EventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];

    public function dispatch(EventInterface $event, string $eventName = null): EventInterface
    {

        if (empty($eventName))
            $eventName = $event::class;

        if (!isset($this->listeners[$eventName]))
            return $event;

        foreach ($this->listeners[$eventName] as $listener) {

            if ($event->isPropagationStopped()) {
                break;
            }

            $listener->onEvent($event, $eventName, $this);
        }

        return $event;
    }

    public function addListener(string $eventName, EventListenerInterface $listener, int $priority = 0)
    {
        $this->listeners[$eventName][] = $listener;
    }

}
