<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Exceptions\UnknownMenuSourceException;
use Ewk\Cms\Layouts\Menus\MenuSource;
use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsMenu;

function categoriesSource(): MenuSource
{
    return new class () implements MenuSource {
        public function code(): string
        {
            return 'categories';
        }

        public function title(): string
        {
            return 'Категории';
        }

        public function fill(CmsMenu $menu): int
        {
            return 3;
        }
    };
}

it('starts empty: the package knows no dictionary of the installation', function (): void {
    $registry = app(MenuSourceRegistry::class);

    expect($registry->all())->toBe([])
        ->and($registry->has('categories'))->toBeFalse()
        ->and(app(MenuSourceRegistry::class))->toBe($registry);
});

it('hands back a registered source by the value stored in the menu', function (): void {
    $registry = app(MenuSourceRegistry::class);
    $source = categoriesSource();

    $registry->register($source);

    expect($registry->has('categories'))->toBeTrue()
        ->and($registry->get('categories'))->toBe($source)
        ->and(array_keys($registry->all()))->toBe(['categories']);
});

it('names the known sources when asked for an unknown one', function (): void {
    $registry = app(MenuSourceRegistry::class);
    $registry->register(categoriesSource());

    expect(fn () => $registry->get('tags'))
        ->toThrow(UnknownMenuSourceException::class, 'known sources: categories');
});
