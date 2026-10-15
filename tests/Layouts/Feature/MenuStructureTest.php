<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Layouts\Exceptions\InvalidMenuItemPlacementException;
use Ewk\Cms\Layouts\Exceptions\UnknownMenuSlotException;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;

it('keeps two levels and refuses a third one', function (): void {
    $menu = Make::menu();
    $root = Make::item($menu, 'About');
    $child = Make::item($menu, 'Team', parent: $root);

    expect($child->parent_id)->toBe($root->id)
        ->and($child->uuid)->not->toBeEmpty();

    expect(fn (): CmsMenuItem => Make::item($menu, 'Photos', parent: $child))
        ->toThrow(InvalidMenuItemPlacementException::class, 'menus hold 2 levels');
});

it('refuses to nest an item that already has children', function (): void {
    $menu = Make::menu();
    $about = Make::item($menu, 'About');
    Make::item($menu, 'Team', parent: $about);
    $company = Make::item($menu, 'Company');

    $about->parent_id = $company->id;

    expect(fn (): bool => $about->save())
        ->toThrow(InvalidMenuItemPlacementException::class, 'third level');
});

it('refuses an item hanging under an item of another menu', function (): void {
    $header = Make::menu('header');
    $footer = Make::menu('footer');
    $root = Make::item($header, 'About');

    expect(fn (): CmsMenuItem => Make::item($footer, 'Team', parent: $root))
        ->toThrow(InvalidMenuItemPlacementException::class, 'another menu');
});

it('refuses an item that is its own parent', function (): void {
    $menu = Make::menu();
    $item = Make::item($menu, 'About');

    $item->parent_id = $item->id;

    expect(fn (): bool => $item->save())
        ->toThrow(InvalidMenuItemPlacementException::class, 'its own parent');
});

it('stores the typed link of the blocks package and reads it back', function (): void {
    $menu = Make::menu();
    $link = new Link(LinkKind::URL, ['url' => 'https://example.test/tickets'], newTab: true);
    $item = Make::item($menu, 'Tickets', $link);

    $stored = $item->fresh();

    expect($stored?->link)->toBe($link->toArray())
        ->and($stored?->toLink()?->kind)->toBe(LinkKind::URL)
        ->and($stored?->toLink()?->attribute('url'))->toBe('https://example.test/tickets')
        ->and($stored?->toLink()?->newTab)->toBeTrue()
        // The caption is a value of the item, never a part of the link.
        ->and($stored?->label)->toBe(['ru' => 'Tickets', 'en' => 'Tickets'])
        ->and($stored?->link)->not->toHaveKey('label');
});

it('allows an item without a link as a group caption', function (): void {
    $menu = Make::menu();
    $item = Make::item($menu, 'Legal');

    expect($item->hasTarget())->toBeFalse()
        ->and($item->toLink())->toBeNull();
});

it('refuses a menu put into a slot the frontend has no place for', function (): void {
    $header = Make::header();
    $menu = Make::menu();

    expect(fn () => Make::slot($header, $menu, 'sidebar'))
        ->toThrow(UnknownMenuSlotException::class, 'Unknown menu slot [sidebar]');
});

it('accepts a slot the installation declared in its configuration', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $footer = Make::footer();
    $menu = Make::menu();

    expect(Make::slot($footer, $menu, 'support')->slot)->toBe('support');
});
