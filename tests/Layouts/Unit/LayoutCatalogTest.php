<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Layouts\LayoutCatalog;
use Ewk\Cms\Layouts\Layouts\LayoutDefinition;

it('starts empty: the package declares no shell of its own', function (): void {
    expect(app(LayoutCatalog::class)->all())->toBe([])
        ->and(app(LayoutCatalog::class)->codes())->toBe([])
        ->and(app(LayoutCatalog::class)->has('default'))->toBeFalse()
        ->and(app(LayoutCatalog::class)->get('default'))->toBeNull();
});

it('hands back the shells the installation declared, in the order it declared them', function (): void {
    $catalog = app(LayoutCatalog::class);

    $catalog->register(LayoutDefinition::make('default', 'Стандартный', 'Обычная страница сайта.'));
    $catalog->register(LayoutDefinition::make('text', 'Текстовая страница'));

    expect($catalog->codes())->toBe(['default', 'text'])
        ->and($catalog->get('default')?->title)->toBe('Стандартный')
        ->and($catalog->get('default')?->description)->toBe('Обычная страница сайта.')
        ->and($catalog->get('text')?->description)->toBe('')
        ->and($catalog->has('bare'))->toBeFalse();
});

it('lets a later declaration replace an earlier one of the same code', function (): void {
    $catalog = app(LayoutCatalog::class);

    $catalog->register(LayoutDefinition::make('default', 'Старое'));
    $catalog->register(LayoutDefinition::make('default', 'Новое'));

    expect($catalog->codes())->toBe(['default'])
        ->and($catalog->get('default')?->title)->toBe('Новое');
});
