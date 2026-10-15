<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\MoonShineCms\Support\MenuSlotLabel;

it('names a declared place in the words of its declaration', function (): void {
    app(MenuSlotCatalog::class)->register(MenuSlotDefinition::make('mail_legal')->label('Письмо: юридические ссылки'));

    expect(MenuSlotLabel::of('mail_legal'))->toBe('Письмо: юридические ссылки')
        ->and(MenuSlotLabel::of('footer_1'))->toBe(__('moonshine-cms::menus.slot_labels.footer_1'));
});

it('falls back to the translations and to the code when nothing bound the catalog', function (): void {
    // The model and this helper stay usable without the provider of the
    // layouts package.
    unset(app()[MenuSlotCatalog::class]);

    expect(app()->bound(MenuSlotCatalog::class))->toBeFalse()
        ->and(MenuSlotLabel::of('footer_1'))->toBe(__('moonshine-cms::menus.slot_labels.footer_1'))
        ->and(MenuSlotLabel::of('mail_legal'))->toBe('mail_legal');
});
