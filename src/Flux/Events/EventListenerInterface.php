<?php
declare(strict_types=1);

namespace Flux\Events;

interface EventListenerInterface
{
    public function onEvent(EventInterface $event, string $eventName, EventDispatcherInterface $eventDispatcher);

}
