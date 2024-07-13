<?php
declare(strict_types=1);

namespace Flux\Events;

interface EventInterface
{
    public function isPropagationStopped(): bool;

    public function stopPropagation(): void;

    public function getSubject(): mixed;

    public function getArgument(string $key): mixed;

    public function setArgument(string $key, mixed $value): static;

    public function getArguments(): array;

    public function setArguments(array $args = array()): static;

    public function hasArgument(string $key): bool;

}

