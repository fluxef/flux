<?php
declare(strict_types=1);

namespace Flux\Events;

class GenericEvent extends Event implements EventInterface
{
    public function __construct(protected mixed $subject = null, protected array $arguments = array())
    {
    }

}
