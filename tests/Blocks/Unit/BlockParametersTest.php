<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\BlockParameters;

it('parses the namespaced sets of a page query', function (): void {
    $parameters = BlockParameters::fromPageQuery([
        'path' => '/',
        'b' => [
            'afisha' => ['shown' => '36', 'date' => '2026-09-12'],
            'raspisanie' => ['page' => '2'],
        ],
    ]);

    expect($parameters->anchors())->toBe(['afisha', 'raspisanie'])
        ->and($parameters->rawFor('afisha'))->toBe(['shown' => '36', 'date' => '2026-09-12'])
        ->and($parameters->rawFor('raspisanie'))->toBe(['page' => '2'])
        ->and($parameters->rawFor('unknown'))->toBe([])
        ->and($parameters->isSingle())->toBeFalse();
});

it('drops anything that is not a set of parameters', function (): void {
    $parameters = BlockParameters::fromPageQuery(['b' => ['afisha' => 'nonsense', 'grid' => ['page' => '2']]]);

    expect($parameters->anchors())->toBe(['grid']);
});

it('treats an empty value as an absent one', function (): void {
    $parameters = BlockParameters::fromPageQuery(['b' => ['afisha' => ['city' => '', 'month' => null, 'page' => '2']]]);

    expect($parameters->rawFor('afisha'))->toBe(['page' => '2']);
});

it('takes the whole flat query for the endpoint of one block', function (): void {
    $parameters = BlockParameters::forSingle('afisha', ['path' => '/', 'page' => '2', 'month' => '2026-01']);

    expect($parameters->anchors())->toBe(['afisha'])
        ->and($parameters->rawFor('afisha'))->toBe(['path' => '/', 'page' => '2', 'month' => '2026-01'])
        ->and($parameters->isSingle())->toBeTrue();
});

it('names a validation error the way the client wrote the address', function (): void {
    expect(BlockParameters::fromPageQuery([])->errorKey('afisha', 'month'))->toBe('b.afisha.month')
        ->and(BlockParameters::forSingle('afisha', [])->errorKey('afisha', 'month'))->toBe('month');
});

it('has no sets at all without a query', function (): void {
    expect(BlockParameters::none()->anchors())->toBe([])
        ->and(BlockParameters::none()->rawFor('afisha'))->toBe([]);
});
