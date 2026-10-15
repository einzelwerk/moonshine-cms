<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Support\ReservedSegments;

it('names the configured prefix an address collides with', function (): void {
    config()->set('cms-pages.reserved_segments', ['api', '/dashboard/orders']);

    $reserved = app(ReservedSegments::class);

    expect($reserved->conflict('/api'))->toBe('/api')
        ->and($reserved->conflict('/api/v1/resolve'))->toBe('/api')
        ->and($reserved->conflict('/dashboard/orders/42'))->toBe('/dashboard/orders')
        ->and($reserved->conflict('/dashboard'))->toBeNull()
        ->and($reserved->conflict('/apiary'))->toBeNull();
});

it('ignores an entry that would reserve the whole site', function (): void {
    config()->set('cms-pages.reserved_segments', ['/', '']);

    $reserved = app(ReservedSegments::class);

    expect($reserved->entries())->toBe([])
        ->and($reserved->conflict('/info'))->toBeNull();
});
