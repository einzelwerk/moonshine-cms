<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Blocks\Links\Actions\LinkAction;

/**
 * The one action the suite registers: a purchase is a state of the frontend
 * with an address of its own.
 */
final class BuyAction implements LinkAction
{
    public function name(): string
    {
        return 'buy';
    }

    public function href(array $params, string $locale): ?string
    {
        return '/concerts/rammstein#buy';
    }
}
