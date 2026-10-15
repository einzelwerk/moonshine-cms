<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Ewk\Cms\Layouts\Exceptions\UnknownMenuSourceException;

/**
 * The sources of menu items an installation declares, keyed by the value
 * they write into `cms_menus.source`. The package ships none: which
 * dictionary feeds a menu is knowledge of the application.
 */
final class MenuSourceRegistry
{
    /** @var array<string, MenuSource> */
    private array $sources = [];

    public function register(MenuSource $source): void
    {
        $this->sources[$source->code()] = $source;
    }

    public function has(string $code): bool
    {
        return isset($this->sources[$code]);
    }

    /**
     * @throws UnknownMenuSourceException
     */
    public function get(string $code): MenuSource
    {
        return $this->sources[$code] ?? throw UnknownMenuSourceException::forCode($code, array_keys($this->sources));
    }

    /**
     * @return array<string, MenuSource>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
