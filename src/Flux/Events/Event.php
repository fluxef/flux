<?php
declare(strict_types=1);

namespace Flux\Events;

class Event implements EventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getArgument(string $key): mixed
    {
        if (isset($this->arguments['key']))
            return $this->arguments['key'];
        return null;
    }

    public function setArgument(string $key, mixed $value): static
    {
        $this->arguments[$key] = $value;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $args = array()): static
    {
        $this->arguments = $args;
        return $this;
    }

    public function hasArgument(string $key): bool
    {
        return isset($this->arguments['key']);
    }


}
