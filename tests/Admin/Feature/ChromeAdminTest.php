<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\MoonShineCms\MoonShine\Resources\FooterResource;
use Ewk\MoonShineCms\MoonShine\Resources\HeaderResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuSlotResource;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('renders the declared content of a header and stores it through the schema', function (): void {
    $resource = app(HeaderResource::class);

    $this->get($resource->getFormPageUrl())
        ->assertOk()
        ->assertSee('name="content[tagline][ru]"', false)
        ->assertSee('name="content[phone]"', false);

    // The declared rules apply: the phone is required.
    submitForm($resource->getRoute('crud.store'), [
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => '1',
        'content' => ['tagline' => ['ru' => 'Билеты']],
    ])->assertStatus(422)->assertJsonValidationErrors(['content.phone']);

    submitForm($resource->getRoute('crud.store'), [
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => '1',
        'content' => ['tagline' => ['ru' => 'Билеты', 'en' => 'Tickets'], 'phone' => '+49 30 1'],
    ])->assertSuccessful();

    $header = CmsHeader::query()->where('code', 'main')->sole();

    expect($header->content)->toBe(['tagline' => ['ru' => 'Билеты', 'en' => 'Tickets'], 'phone' => '+49 30 1']);

    // The table of the menus appears once the row exists: a place belongs to a
    // record, and a record that does not exist yet owns none. The caption
    // names the kind of the form and not both kinds at once.
    $this->get($resource->getFormPageUrl())
        ->assertOk()
        ->assertDontSee(__('moonshine-cms::chrome.header_slots'), false);

    $this->get($resource->getFormPageUrl($header->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::chrome.header_slots'), false);
});

it('names a refused field of the content by its caption and not by its path', function (): void {
    $resource = app(HeaderResource::class);

    $response = submitForm($resource->getRoute('crud.store'), [
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => '1',
        'content' => ['tagline' => ['ru' => str_repeat('а', 61)]],
    ])->assertStatus(422)->assertJsonValidationErrors(['content.phone', 'content.tagline.ru']);

    $errors = $response->json('errors');

    expect($errors['content.phone'][0])->toContain('Телефон')
        ->and($errors['content.phone'][0])->not->toContain('content.')
        // A translation is named with its language: the switch of the form
        // shows one language at a time.
        ->and($errors['content.tagline.ru'][0])->toContain('Слоган (RU)');
});

it('stores the logo of a header as a reference and never as a file', function (): void {
    $resource = app(HeaderResource::class);
    $media = CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'cms/logo.svg',
        'original_name' => 'logo.svg',
        'name' => 'Логотип',
        'mime' => 'image/svg+xml',
        'size' => 1024,
    ]);

    submitForm($resource->getRoute('crud.store'), [
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => '1',
        'content' => [
            'tagline' => ['ru' => 'Билеты'],
            'phone' => '+49 30 1',
            'logo' => ['id' => $media->uuid, 'metadata' => ['alt' => ['ru' => ' Логотип фестиваля ', 'en' => '']]],
        ],
    ])->assertSuccessful();

    $header = CmsHeader::query()->where('code', 'main')->sole();

    expect($header->content['logo'])->toBe([
        'id' => $media->uuid,
        'metadata' => ['alt' => ['ru' => 'Логотип фестиваля']],
    ]);

    // And the form opens on the picture, with the description of this place
    // in the input of its language.
    $this->get($resource->getFormPageUrl($header->getKey()))
        ->assertOk()
        ->assertSee('name="content[logo][id]"', false)
        ->assertSee('name="content[logo][metadata][alt][ru]"', false)
        ->assertSee('Логотип фестиваля', false);
});

it('draws the dialog of the library once for a form with two pictures', function (): void {
    $resource = app(HeaderResource::class);
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Шапка'], 'is_active' => true]);

    $html = $this->get($resource->getFormPageUrl($header->getKey()))->assertOk()->getContent();

    expect($html)->toContain('name="content[logo][id]"')
        ->and($html)->toContain('name="content[badge][id]"')
        // One dialog per document: the first field draws it and the second
        // one opens it, see \Ewk\Cms\Media\MoonShine\Library\PickerModal.
        ->and(pickerDialogCount($html))->toBe(1);
});

it('saves a footer whose kind declares no content', function (): void {
    $resource = app(FooterResource::class);

    $this->get($resource->getFormPageUrl())
        ->assertOk()
        ->assertDontSee(__('moonshine-cms::chrome.tab_content'), false);

    submitForm($resource->getRoute('crud.store'), [
        'code' => 'main',
        'name' => ['ru' => 'Подвал'],
        'is_active' => '1',
    ])->assertSuccessful();

    expect(CmsFooter::query()->where('code', 'main')->exists())->toBeTrue();
});

it('puts a menu into a slot of the closed dictionary only', function (): void {
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Шапка'], 'is_active' => true]);
    $menu = CmsMenu::query()->create(['code' => 'primary', 'name' => ['ru' => 'Основное'], 'is_active' => true]);
    $other = CmsMenu::query()->create(['code' => 'secondary', 'name' => ['ru' => 'Второе'], 'is_active' => true]);

    // The row is created from the form of its header, which is stock
    // MoonShine; what is ours is the form of the row itself.
    $assignment = $header->menuSlots()->create([
        'slot' => 'header_primary',
        'cms_menu_id' => $menu->getKey(),
        'sort_order' => 1,
        'is_enabled' => true,
    ]);

    $update = app(MenuSlotResource::class)->getRoute('crud.update', $assignment->getKey());

    submitForm($update, ['slot' => 'sidebar', 'cms_menu_id' => $other->getKey()], 'PUT')
        ->assertStatus(422)->assertJsonValidationErrors(['slot']);

    // A place declared for another record is no place of a header.
    app(MenuSlotCatalog::class)->register(MenuSlotDefinition::make('mail_legal'));

    submitForm($update, ['slot' => 'mail_legal', 'cms_menu_id' => $other->getKey()], 'PUT')
        ->assertStatus(422)->assertJsonValidationErrors(['slot']);

    submitForm($update, ['slot' => 'footer_1', 'cms_menu_id' => $other->getKey(), 'is_enabled' => '1'], 'PUT')
        ->assertSuccessful();

    $assignment->refresh();

    expect($assignment->slot)->toBe('footer_1')
        ->and($assignment->owner_id)->toBe($header->getKey())
        ->and($assignment->cms_menu_id)->toBe($other->getKey());
});

it('builds the code of a header from its name and never rewrites it on a rename', function (): void {
    $resource = app(HeaderResource::class);

    $this->get($resource->getFormPageUrl())
        ->assertOk()
        ->assertSee('data-cms-slug-target="code"', false)
        ->assertSee('data-cms-slug-source', false);

    submitForm($resource->getRoute('crud.store'), [
        'name' => ['ru' => 'Основная шапка'],
        'is_active' => '1',
        'content' => ['tagline' => ['ru' => 'Билеты'], 'phone' => '+49 30 1'],
    ])->assertSuccessful();

    $header = CmsHeader::query()->sole();

    expect($header->code)->toBe('osnovnaya-shapka');

    submitForm(
        $resource->getRoute('crud.update', $header->getKey()),
        [
            'name' => ['ru' => 'Шапка лендинга'],
            'code' => $header->code,
            'is_active' => '1',
            'content' => ['tagline' => ['ru' => 'Билеты'], 'phone' => '+49 30 1'],
        ],
        'PUT',
    )->assertSuccessful();

    expect($header->refresh()->code)->toBe('osnovnaya-shapka');
});

it('draws the declared content in the language the switch of the form selects', function (): void {
    $header = CmsHeader::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => true,
        'content' => [],
    ]);

    $html = (string) $this->get(app(HeaderResource::class)->getFormPageUrl($header->getKey()))->assertOk()->getContent();

    // The translatable value stays in the place the schema declares it and
    // carries the language on the wrapper, instead of a tab strip at the end.
    expect($html)->toContain('data-cms-locale-switch="ru"')
        ->and($html)->toContain('data-cms-locale="en"')
        ->and($html)->toContain('name="content[tagline][en]"')
        ->and($html)->not->toContain('Tagline (EN)');
});

it('keeps the vertical rhythm of the panel on a form it wraps in its own scope', function (): void {
    $header = CmsHeader::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Шапка'],
        'is_active' => true,
        'content' => [],
    ]);

    $html = (string) $this->get(app(HeaderResource::class)->getFormPageUrl($header->getKey()))->assertOk()->getContent();

    // MoonShine spaces the direct children of a `<form>`, and the scope of the
    // language switch stands between the two. Without the class of the panel
    // on the wrapper the fields of every entity form stick together.
    expect($html)->toContain('class="cms-form space-elements"');
});

it('names the places of the slots in words', function (): void {
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Шапка'], 'is_active' => true, 'content' => []]);
    $menu = CmsMenu::query()->create(['code' => 'primary', 'name' => ['ru' => 'Основное'], 'is_active' => true]);

    $assignment = $header->menuSlots()->create([
        'slot' => 'header_primary',
        'cms_menu_id' => $menu->getKey(),
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    $this->get(app(MenuSlotResource::class)->getFormPageUrl($assignment->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::menus.slot_labels.header_primary'), false)
        // No order field anywhere: the order of a place is the order of its rows.
        ->assertDontSee('name="sort_order"', false);
});
