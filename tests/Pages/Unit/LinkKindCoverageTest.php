<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * Every kind of link belongs to the first version, and a kind without a
 * resolver is now refused by the field rather than stored as a link that
 * silently leads nowhere, see {@see \Ewk\Cms\Blocks\Fields\LinkField}. So the
 * two packages that own addresses have to cover the enum between them: blocks
 * answer `url`, `email`, `phone`, `anchor`, `file` and `action`, pages answer
 * `page`, `list`, `entity` and `route`.
 *
 * This is the test that fails when a kind is added to the enum and forgotten,
 * and the test that fails when a resolver stops being registered — which is
 * exactly how `entity` and `anchor` came to be offered in five blocks of the
 * application with nothing standing behind them.
 */
it('has a resolver for every kind of link once the parts of the CMS are registered', function (): void {
    $registry = app(LinkResolverRegistry::class);

    $unresolved = array_values(array_filter(
        LinkKind::cases(),
        static fn (LinkKind $kind): bool => ! $registry->has($kind),
    ));

    expect(array_map(static fn (LinkKind $kind): string => $kind->value, $unresolved))->toBe([]);
});
