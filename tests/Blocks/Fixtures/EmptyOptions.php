<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Params\OptionsProvider;

/**
 * A provider with nothing to offer: no city has an event today.
 */
final class EmptyOptions implements OptionsProvider
{
    public function options(): array
    {
        return [];
    }
}
