<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Actions;

/**
 * Actions arrive from a registry, the way blocks and field types do. The
 * package registers none: the address of an action is built out of the entities
 * of an installation, and those live in the application.
 */
final class ActionRegistry
{
    /** @var array<string, LinkAction> */
    private array $actions = [];

    public function register(LinkAction $action): void
    {
        $this->actions[$action->name()] = $action;
    }

    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    public function get(string $name): ?LinkAction
    {
        return $this->actions[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->actions);
    }
}
