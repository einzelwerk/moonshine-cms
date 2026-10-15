<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Menus\MenuSlot;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\Cms\Layouts\Menus\MenuSlotOwner;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Illuminate\Database\Eloquent\Model;

it('ships the closed slot dictionary', function (): void {
    expect(MenuSlot::codes())->toBe([
        'header_primary',
        'header_secondary',
        'footer_1',
        'footer_2',
        'footer_3',
        'legal',
        'social',
    ]);
});

it('extends the dictionary with the slots of the installation instead of replacing it', function (): void {
    config()->set('cms-layouts.menu_slots', ['support', 'header_primary', ' apps ', '', 42]);

    $catalog = app(MenuSlotCatalog::class);

    expect($catalog->codes())->toBe([...MenuSlot::codes(), 'support', 'apps'])
        ->and($catalog->has('social'))->toBeTrue()
        ->and($catalog->has('apps'))->toBeTrue()
        ->and($catalog->has('sidebar'))->toBeFalse();
});

it('says which part of the chrome owns a place', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $catalog = app(MenuSlotCatalog::class);

    expect($catalog->kindOf('header_primary'))->toBe(ChromeKind::HEADER)
        ->and($catalog->kindOf('header_secondary'))->toBe(ChromeKind::HEADER)
        ->and($catalog->kindOf('footer_2'))->toBe(ChromeKind::FOOTER)
        ->and($catalog->kindOf('legal'))->toBe(ChromeKind::FOOTER)
        ->and($catalog->kindOf('social'))->toBe(ChromeKind::FOOTER)
        // A place the installation added says nothing about its owner: it is
        // filled on the form of the header or the footer itself.
        ->and($catalog->kindOf('support'))->toBeNull()
        ->and($catalog->kindOf('sidebar'))->toBeNull();
});

it('adds the declared places after the places of the chrome', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $catalog = app(MenuSlotCatalog::class);
    $catalog->register(MenuSlotDefinition::make(' mail_legal ')->label('Письмо: юридические ссылки'));
    $catalog->register(MenuSlotDefinition::make('mail_social'));

    expect($catalog->codes())->toBe([...MenuSlot::codes(), 'support', 'mail_legal', 'mail_social'])
        ->and($catalog->chromeCodes())->toBe([...MenuSlot::codes(), 'support'])
        ->and($catalog->has('mail_legal'))->toBeTrue()
        ->and($catalog->isChromeSlot('mail_legal'))->toBeFalse()
        ->and($catalog->isChromeSlot('support'))->toBeTrue()
        ->and($catalog->isChromeSlot('footer_1'))->toBeTrue()
        // kindOf() answers for the chrome only, a declared place included.
        ->and($catalog->kindOf('mail_legal'))->toBeNull()
        ->and(array_map(static fn (MenuSlotDefinition $definition): string => $definition->code(), $catalog->definitions()))
            ->toBe(['mail_legal', 'mail_social']);
});

it('offers on the form of a menu only the places whose owner is known', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $catalog = app(MenuSlotCatalog::class);
    $catalog->register(MenuSlotDefinition::make('mail_legal'));

    expect($catalog->isAssignable('footer_1'))->toBeTrue()
        ->and($catalog->isAssignable('mail_legal'))->toBeTrue()
        ->and($catalog->isAssignable('support'))->toBeFalse()
        ->and($catalog->isAssignable('sidebar'))->toBeFalse();
});

it('names a declared place in its own words and leaves the rest to the panel', function (): void {
    $catalog = app(MenuSlotCatalog::class);
    $locale = 'ru';

    $catalog->register(MenuSlotDefinition::make('mail_legal')->label(static function () use (&$locale): string {
        return $locale === 'ru' ? 'Письмо: юридические ссылки' : 'Letter: legal links';
    }));
    $catalog->register(MenuSlotDefinition::make('mail_social'));

    expect($catalog->labelOf('mail_legal'))->toBe('Письмо: юридические ссылки');

    // The caption is asked on every read, so it follows the locale.
    $locale = 'en';

    expect($catalog->labelOf('mail_legal'))->toBe('Letter: legal links')
        // No words given: the code, the same fallback the panel uses.
        ->and($catalog->labelOf('mail_social'))->toBe('mail_social')
        ->and($catalog->labelOf('footer_1'))->toBeNull()
        ->and($catalog->labelOf('sidebar'))->toBeNull();
});

it('refuses a second place with a code that is already known', function (string $code): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $catalog = app(MenuSlotCatalog::class);
    $catalog->register(MenuSlotDefinition::make('mail_legal'));

    $catalog->register(MenuSlotDefinition::make($code));
})->with(['footer_1', 'support', 'mail_legal'])->throws(InvalidArgumentException::class);

it('refuses a place without a code', function (): void {
    MenuSlotDefinition::make('  ');
})->throws(InvalidArgumentException::class);

it('hangs a declared place on the record its declaration names', function (): void {
    $catalog = app(MenuSlotCatalog::class);
    $owner = Make::footer('letter');
    $exists = false;

    $catalog->register(MenuSlotDefinition::make('mail_legal')->ownedBy(static fn (): CmsFooter => $owner));
    $catalog->register(MenuSlotDefinition::make('mail_social')->ownedBy(new class ($exists) implements MenuSlotOwner {
        public function __construct(private bool &$exists)
        {
        }

        public function resolve(): ?Model
        {
            return $this->exists ? CmsFooter::query()->where('code', 'letter')->first() : null;
        }
    }));
    $catalog->register(MenuSlotDefinition::make('mail_other'));

    expect($catalog->ownerOf('mail_legal')?->is($owner))->toBeTrue()
        // The owner does not exist yet, or none was declared at all.
        ->and($catalog->ownerOf('mail_social'))->toBeNull()
        ->and($catalog->ownerOf('mail_other'))->toBeNull();

    $exists = true;

    expect($catalog->ownerOf('mail_social')?->is($owner))->toBeTrue();
});

it('hangs a place of the chrome on the record that fills it, then on the first active one', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);

    $catalog = app(MenuSlotCatalog::class);

    // Nothing of the kind yet.
    expect($catalog->ownerOf('footer_1'))->toBeNull();

    $inactive = Make::footer('old', active: false);

    // Only a switched off one: still better than nowhere.
    expect($catalog->ownerOf('footer_1')?->is($inactive))->toBeTrue();

    $active = Make::footer('main');
    $header = Make::header('main');

    expect($catalog->ownerOf('footer_1')?->is($active))->toBeTrue()
        ->and($catalog->ownerOf('header_primary')?->is($header))->toBeTrue();

    // The record that already fills the place wins over the first active one.
    Make::slot($inactive, Make::menu('columns'), 'footer_1');

    expect($catalog->ownerOf('footer_1')?->is($inactive))->toBeTrue()
        // A configured place has no owner to read off anything.
        ->and($catalog->ownerOf('support'))->toBeNull()
        ->and($catalog->ownerOf('sidebar'))->toBeNull();
});

it('shows the code when the words of a declaration come out empty', function (): void {
    $definition = MenuSlotDefinition::make('mail_legal')->label(static fn (): string => '');

    expect($definition->caption())->toBe('mail_legal')
        ->and(MenuSlotDefinition::make('mail_social')->label('')->caption())->toBe('mail_social');
});
