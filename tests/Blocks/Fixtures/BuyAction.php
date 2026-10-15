<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Links\Actions\LinkAction;

/**
 * The purchase action of the first version, in the shape an application
 * declares it: the address is a full one, and the anchor it ends in is the form
 * the frontend chose — the contract fixes only that the state is addressable,
 * not how.
 */
final class BuyAction implements LinkAction
{
    public function name(): string
    {
        return 'buy';
    }

    public function href(array $params, string $locale): ?string
    {
        $event = $params['event'] ?? null;

        if (! is_string($event) || $event === '') {
            return null;
        }

        return '/tours/stand-up/'.$event.'#buy';
    }
}
