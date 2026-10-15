<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Selection\LayoutSelection;
use Ewk\Cms\Layouts\Selection\LayoutSelector;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;

it('takes the layout the page names', function (): void {
    Make::layout('default', default: true);
    Make::layout('text', sortOrder: 1);

    $selected = app(LayoutSelector::class)->select('text');

    expect($selected->outcome)->toBe(LayoutSelection::REQUESTED)
        ->and($selected->code())->toBe('text')
        ->and($selected->fellBack())->toBeFalse();
});

it('falls back to the default layout when the page names none', function (): void {
    Make::layout('text');
    Make::layout('default', default: true, sortOrder: 1);

    $selected = app(LayoutSelector::class)->select(null);

    expect($selected->outcome)->toBe(LayoutSelection::DEFAULT)
        ->and($selected->code())->toBe('default')
        ->and($selected->found)->toBeTrue()
        // Nothing was asked for, so nothing was refused.
        ->and($selected->fellBack())->toBeFalse();
});

it('falls back to the default when the named layout is unknown or switched off', function (): void {
    Make::layout('default', default: true);
    Make::layout('retired', active: false, sortOrder: 1);

    $unknown = app(LayoutSelector::class)->select('mystery');
    $inactive = app(LayoutSelector::class)->select('retired');

    expect($unknown->code())->toBe('default')
        ->and($unknown->requestedCode)->toBe('mystery')
        ->and($unknown->fellBack())->toBeTrue()
        ->and($inactive->code())->toBe('default')
        ->and($inactive->fellBack())->toBeTrue();
});

it('reports a missing default instead of handing out a silent null', function (): void {
    Make::layout('text');

    $selected = app(LayoutSelector::class)->select('mystery');

    expect($selected->outcome)->toBe(LayoutSelection::NO_DEFAULT)
        ->and($selected->found)->toBeFalse()
        ->and($selected->layout)->toBeNull()
        ->and($selected->code())->toBeNull()
        ->and($selected->requestedCode)->toBe('mystery');
});
