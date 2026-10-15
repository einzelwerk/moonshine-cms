<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\Params\OptionsProvider;
use Ewk\Cms\Pages\Support\SubjectContext;
use Ewk\Cms\Pages\Tests\Fixtures\Concert;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * A set of values computed from the entity of the page, the way the real
 * providers of an installation do it: the months of a schedule, the cities of
 * a catalog.
 *
 * It asks the container for the request, which is the whole point of the
 * fixture: the resolver has to attach the subject to that very object, not to
 * a copy of its own. A provider that sees no subject offers nothing, and an
 * empty set refuses every value — so a page whose subject went missing would
 * answer 422 on a value that is perfectly valid.
 */
final readonly class SubjectOptions implements OptionsProvider
{
    public function options(): array
    {
        $subject = SubjectContext::any(Container::getInstance()->make(Request::class));

        return $subject instanceof Concert ? [$subject->slug => $subject->slug] : [];
    }
}
