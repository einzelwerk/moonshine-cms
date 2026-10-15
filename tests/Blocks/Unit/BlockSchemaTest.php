<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

it('defaults to a root-only shareable placement without a category', function (): void {
    $schema = BlockSchema::make()->title('Текст');

    expect($schema->category())->toBeNull()
        ->and($schema->variants())->toBe([])
        ->and($schema->fields())->toBe([])
        ->and($schema->placementRoot())->toBe(['*'])
        ->and($schema->placementParents())->toBe([])
        ->and($schema->isShareable())->toBeTrue();
});

it('finds a declared field by name', function (): void {
    $schema = BlockSchema::make()->fields([TextField::make('title')]);

    expect($schema->hasField('title'))->toBeTrue()
        ->and($schema->hasField('body'))->toBeFalse()
        ->and($schema->field('title')?->name())->toBe('title')
        ->and($schema->field('body'))->toBeNull();
});

it('reads back the declaration of a dynamic block', function (): void {
    $month = StringParam::make('month')->pattern('^\d{4}-(0[1-9]|1[0-2])$')->nullable();
    $city = EnumParam::make('city')->options(['berlin' => 'Берлин'])->nullable()->filter();

    $schema = BlockSchema::make()
        ->params([$month, $city])
        ->pagination(mode: PaginationMode::PAGES, perPage: 5, maxPerPage: 20, editable: true)
        ->sort('event_date', 'desc');

    expect($schema->hasParams())->toBeTrue()
        ->and($schema->param('city'))->toBe($city)
        ->and($schema->param('unknown'))->toBeNull()
        ->and($schema->pagination()?->maxShown)->toBeNull()
        ->and($schema->pagination()?->continuation)->toBe(Continuation::URL)
        ->and($schema->sort()?->field)->toBe('event_date')
        ->and($schema->sort()?->direction)->toBe('desc');
});

it('declares nothing dynamic until parameters or a continuation appear', function (): void {
    $plain = BlockSchema::make()->fields([TextField::make('title')]);
    $fixed = BlockSchema::make()->pagination(mode: PaginationMode::NONE);
    $paginated = BlockSchema::make()->pagination(mode: PaginationMode::MORE);
    $parameterized = BlockSchema::make()->params([StringParam::make('month')]);

    expect($plain->isDynamic())->toBeFalse()
        ->and($plain->params())->toBe([])
        ->and($plain->pagination())->toBeNull()
        ->and($plain->sort())->toBeNull()
        ->and($fixed->isDynamic())->toBeFalse()
        ->and($paginated->isDynamic())->toBeTrue()
        ->and($parameterized->isDynamic())->toBeTrue();
});

it('reads back a pagination whose mode the editor may change', function (): void {
    $schema = BlockSchema::make()->pagination(
        mode: PaginationMode::NONE,
        perPage: 6,
        modeEditable: true,
        allowedModes: [PaginationMode::NONE, PaginationMode::MORE],
    );

    expect($schema->pagination()?->modeEditable)->toBeTrue()
        ->and($schema->pagination()?->allowedModes)->toBe([PaginationMode::NONE, PaginationMode::MORE])
        ->and($schema->pagination()?->allows(PaginationMode::MORE))->toBeTrue()
        ->and($schema->pagination()?->allows(PaginationMode::PAGES))->toBeFalse()
        // Declared without a continuation, and still dynamic: the editor is
        // allowed to turn one on.
        ->and($schema->isDynamic())->toBeTrue();
});

it('allows every mode when the allowed ones are not spelled out', function (): void {
    $pagination = BlockSchema::make()
        ->pagination(mode: PaginationMode::PAGES, modeEditable: true)
        ->pagination();

    expect($pagination?->allows(PaginationMode::NONE))->toBeTrue()
        ->and($pagination?->allows(PaginationMode::MORE))->toBeTrue()
        ->and($pagination?->allows(PaginationMode::PAGES))->toBeTrue();
});

it('stays static when the mode is fixed at none', function (): void {
    expect(BlockSchema::make()->pagination(mode: PaginationMode::NONE, modeEditable: false)->isDynamic())
        ->toBeFalse();
});
