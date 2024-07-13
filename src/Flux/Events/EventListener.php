<?php
declare(strict_types=1);

namespace Flux\Events;

abstract class EventListener implements EventListenerInterface
{
    public function __construct(protected mixed $subject = null, protected array $arguments = array())
    {
    }

    abstract public function onEvent(EventInterface $event, string $eventName, EventDispatcherInterface $eventDispatcher);

}
