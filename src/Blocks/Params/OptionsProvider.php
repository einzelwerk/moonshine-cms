<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * Values an {@see EnumParam} accepts when the set is not known at declaration
 * time: the cities of the catalog, the months of a schedule. The provider is
 * resolved through the container at the moment it is asked, never held by the
 * declaration, so a schema stays a plain description.
 */
interface OptionsProvider
{
    /**
     * @return array<string|int, string> value => label
     */
    public function options(): array;
}
