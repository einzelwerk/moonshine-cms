<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Menus\MenuSlot;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Layouts\Tests\Fixtures\TestChromeSchemas;

/*
 * The two endpoints of the chrome. They live in this package, so the routes are
 * registered by its own provider and the tests drive them over HTTP.
 */

beforeEach(function (): void {
    TestChromeSchemas::registerAll(app(ChromeSchemaRegistry::class));

    // Turning `Accept-Language` into a locale is middleware work and belongs to
    // the installation; the document answers in the locale of the application.
    app()->setLocale('ru');
});

it('delivers layouts, header, footer and the menus of their slots in one document', function (): void {
    $header = Make::header();
    $footer = Make::footer(content: ['copyright' => ['ru' => '© Acme']]);
    Make::layout(default: true, header: $header, footer: $footer);

    $menu = Make::menu('footer_1');
    Make::item($menu, 'Условия', new Link(LinkKind::URL, ['url' => 'https://example.test/terms']));
    Make::slot($footer, $menu, MenuSlot::FOOTER_FIRST->value);

    $response = $this->getJson('/api/v1/chrome');

    $response->assertOk()
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.locale', 'ru')
        ->assertJsonPath('data.layouts.0.code', 'default')
        ->assertJsonPath('data.layouts.0.header', 'main')
        ->assertJsonPath('data.layouts.0.footer', 'main')
        ->assertJsonPath('data.layouts.0.is_default', true)
        ->assertJsonPath('data.footers.0.slots.footer_1', ['footer_1'])
        ->assertJsonPath('data.footers.0.content.copyright', '© Acme')
        ->assertJsonPath('data.menus.0.items.0.label', 'Условия')
        ->assertJsonPath('data.menus.0.items.0.link.href', 'https://example.test/terms')
        ->assertJsonPath('data.menus.0.items.0.broken', false);
});

it('fetches a single menu by its code and answers 404 for an unknown or switched off one', function (): void {
    $menu = Make::menu('legal');
    Make::item($menu, 'Условия', new Link(LinkKind::URL, ['url' => 'https://example.test/terms']));

    $this->getJson('/api/v1/menus/legal')
        ->assertOk()
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.code', 'legal')
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.items.0.link.href', 'https://example.test/terms');

    // A miss depends on the language just as a hit does, so it carries the
    // header too.
    $this->getJson('/api/v1/menus/nope')
        ->assertNotFound()
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('message', __('cms-layouts::chrome.menu_not_found'));

    $menu->update(['is_active' => false]);

    $this->getJson('/api/v1/menus/legal')->assertNotFound();
});
