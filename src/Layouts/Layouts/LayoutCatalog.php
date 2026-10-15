<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Layouts;

/**
 * The shells this installation knows. The package ships none: a code without a
 * component in the frontend is an unkept promise, so the list is declared by
 * whoever ships that frontend, the way {@see
 * \Ewk\Cms\Layouts\Menus\MenuSourceRegistry} holds the dictionaries a menu is
 * filled from.
 *
 * The admin panel reads it to decide what may exist: a row of `cms_layouts`
 * whose code is not declared here is still drawn and still edited, because it
 * was seeded before the declaration, but no new code is invented in a form.
 */
final class LayoutCatalog
{
    /** @var array<string, LayoutDefinition> */
    private array $layouts = [];

    public function register(LayoutDefinition $layout): void
    {
        $this->layouts[$layout->code] = $layout;
    }

    public function has(string $code): bool
    {
        return isset($this->layouts[$code]);
    }

    public function get(string $code): ?LayoutDefinition
    {
        return $this->layouts[$code] ?? null;
    }

    /**
     * @return array<string, LayoutDefinition>
     */
    public function all(): array
    {
        return $this->layouts;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->layouts);
    }
}
