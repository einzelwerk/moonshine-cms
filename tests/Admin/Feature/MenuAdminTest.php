<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\Cms\Layouts\Menus\MenuSource;
use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\MoonShineCms\Actions\SaveMenu;
use Ewk\MoonShineCms\MoonShine\Components\MenuTreeView;
use Ewk\MoonShineCms\MoonShine\Resources\MenuItemResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuItemFormPage;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function menu(string $source = CmsMenu::SOURCE_MANUAL): CmsMenu
{
    return CmsMenu::query()->create([
        'code' => 'header_primary',
        'name' => ['ru' => 'Меню'],
        'source' => $source,
        'is_active' => true,
    ]);
}

function menuItem(CmsMenu $menu, string $label, ?CmsMenuItem $parent = null): CmsMenuItem
{
    return $menu->items()->create([
        'label' => ['ru' => $label],
        'parent_id' => $parent?->getKey(),
        'sort_order' => 1,
        'is_active' => true,
    ]);
}

/**
 * A dictionary of the installation: fills the menu with two fixed entries.
 */
function tagsSource(): MenuSource
{
    return new class () implements MenuSource {
        public function code(): string
        {
            return 'tags';
        }

        public function title(): string
        {
            return 'Теги';
        }

        public function fill(CmsMenu $menu): int
        {
            $menu->items()->delete();

            foreach (['Концерты', 'Театр'] as $position => $label) {
                $menu->items()->create(['label' => ['ru' => $label], 'sort_order' => $position + 1, 'is_active' => true]);
            }

            return 2;
        }
    };
}

it('refuses to hang an item under a child item and keeps it where it was', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');
    $child = menuItem($menu, 'О компании', $root);
    $loose = menuItem($menu, 'Контакты');

    // The form of the panel saves over ajax, and that is the path where a
    // refusal comes back as a field error instead of a bare toast.
    $response = submitForm(
        app(MenuItemResource::class)->getRoute('crud.update', $loose->getKey()),
        [
            'label' => ['ru' => 'Контакты'],
            'parent_id' => $child->getKey(),
            'is_active' => '1',
        ],
        'PUT',
    );

    $response->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('parent_id')
        ->and($loose->refresh()->parent_id)->toBeNull();
});

it('accepts a second level and stores the target as a typed link', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');
    $loose = menuItem($menu, 'Условия');
    $page = cmsPage(['slug' => 'terms']);

    submitForm(
        app(MenuItemResource::class)->getRoute('crud.update', $loose->getKey()),
        [
            'label' => ['ru' => 'Условия'],
            'parent_id' => $root->getKey(),
            'is_active' => '1',
            'link' => [
                'kind' => LinkKind::PAGE->value,
                'attributes' => ['page' => $page->uuid],
                'new_tab' => '0',
            ],
        ],
        'PUT',
    )->assertSuccessful();

    $loose->refresh();

    expect($loose->parent_id)->toBe($root->getKey())
        ->and($loose->link['kind'] ?? null)->toBe(LinkKind::PAGE->value)
        ->and($loose->link['attributes']['page'] ?? null)->toBe($page->uuid);
});

it('offers the registered sources next to the manual one and refuses an unknown one', function (): void {
    app(MenuSourceRegistry::class)->register(tagsSource());
    $store = app(MenuResource::class)->getRoute('crud.store');

    $this->get(app(MenuResource::class)->getFormPageUrl())
        ->assertOk()
        ->assertSee('Теги', false)
        ->assertSee(__('moonshine-cms::menus.source_manual'), false);

    submitForm($store, ['code' => 'footer', 'name' => ['ru' => 'Подвал'], 'source' => 'categories'])
        ->assertStatus(422)->assertJsonValidationErrors(['source']);

    submitForm($store, ['code' => 'footer', 'name' => ['ru' => 'Подвал'], 'source' => 'tags'])
        ->assertSuccessful();

    expect(CmsMenu::query()->where('code', 'footer')->sole()->source)->toBe('tags');
});

it('fills a menu from its source on request and shows the button only there', function (): void {
    app(MenuSourceRegistry::class)->register(tagsSource());

    $manual = menu();
    $fed = CmsMenu::query()->create(['code' => 'tags', 'name' => ['ru' => 'Теги'], 'source' => 'tags', 'is_active' => true]);
    $resource = app(MenuResource::class);

    $fill = static fn (CmsMenu $menu): string => $resource->getAsyncMethodUrl(
        'fillFromSource',
        params: ['resourceItem' => $menu->getKey()],
        page: $resource->getFormPage(),
    );

    // The button posts the address instead of linking it, so the document
    // carries the name of the command and not an escaped url to compare.
    $this->get($resource->getFormPageUrl($fed->getKey()))->assertOk()->assertSee('fillFromSource', false);
    $this->get($resource->getFormPageUrl($manual->getKey()))->assertOk()->assertDontSee('fillFromSource', false);

    // Filling rewrites the items, so the address does nothing on a GET.
    $this->get($fill($fed))->assertStatus(405);

    expect($fed->items()->count())->toBe(0);

    $this->post($fill($fed))->assertOk()->assertJsonPath('redirect', $resource->getFormPageUrl($fed->getKey()));

    expect($fed->items()->pluck('label')->map(static fn (array $label): string => $label['ru'])->all())
        ->toBe(['Концерты', 'Театр']);

    // A manual menu has nothing to fill from.
    $this->post($fill($manual))->assertStatus(422);

    expect($manual->items()->count())->toBe(0);
});

function menuResource(): MenuResource
{
    return app(MenuResource::class);
}

/**
 * The move command as the tree posts it: the address names the menu, and a
 * command that reaches past it is refused.
 */
function moveMenuItemUrl(CmsMenu $menu): string
{
    return menuResource()->getAsyncMethodUrl(
        'moveMenuItem',
        params: ['resourceItem' => $menu->getKey()],
        page: menuResource()->getFormPage(),
    );
}

/**
 * The tree as the panel draws it on the form of the menu.
 */
function menuTreeHtml(CmsMenu $menu): string
{
    return (string) test()
        ->get(menuResource()->getFormPageUrl($menu->getKey()).'?_fragment-load='.MenuTreeView::FRAGMENT)
        ->assertOk()
        ->getContent();
}

it('draws the items nested under their parents with a way to add a nested one', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');
    $child = menuItem($menu, 'О компании', $root);
    $loose = menuItem($menu, 'Контакты');

    $html = menuTreeHtml($menu);

    expect($html)->toContain('data-cms-menu-item="'.$root->getKey().'"')
        ->and($html)->toContain('Информация')
        ->and($html)->toContain('О компании')
        ->and($html)->toContain('Контакты')
        // The child sits inside the list item of its parent.
        ->and(strpos($html, 'data-cms-menu-item="'.$child->getKey().'"'))
            ->toBeGreaterThan(strpos($html, 'data-cms-menu-item="'.$root->getKey().'"'))
        // A root row offers a nested item, a second level row does not.
        ->and($html)->toContain('data-cms-menu-add-child="'.$loose->getKey().'"')
        ->and($html)->not->toContain('data-cms-menu-add-child="'.$child->getKey().'"')
        // The order is dragged and never typed: the containers of the standard
        // sortable carry the parent, and no item has an order field.
        ->and($html)->toContain('data-cms-menu-container')
        ->and($html)->toContain('data-id="'.$root->getKey().'"')
        ->and($html)->not->toContain('name="sort_order"');
});

it('opens an item and a new one in a dialog over the tree', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');

    $html = menuTreeHtml($menu);

    expect($html)->toContain('data-cms-menu-edit="'.$root->getKey().'"')
        // The caption and the pencil open one dialog, «add» opens one of its own.
        ->and($html)->toContain('modal_toggled:cms-menu-item-'.$root->getKey().'.window')
        ->and($html)->toContain("\$dispatch('modal_toggled:cms-menu-item-".$root->getKey()."')")
        ->and($html)->toContain('modal_toggled:cms-menu-item-new.window')
        ->and($html)->toContain('modal_toggled:cms-menu-item-new-'.$root->getKey().'.window')
        // The dialog loads the form alone, in the mode that saves in place.
        ->and($html)->toContain(MenuItemFormPage::MODAL.'=1')
        ->and($html)->toContain('_fragment-load=crud-form');
});

it('saves an item from the dialog without leaving the menu', function (): void {
    $menu = menu();
    $item = menuItem($menu, 'Информация');

    $form = (string) $this->get(app(MenuItemResource::class)->getFormPageUrl($item->getKey(), params: [
        'menu' => $menu->getKey(),
        MenuItemFormPage::MODAL => 1,
        '_async_form' => 1,
    ], fragment: 'crud-form'))->assertOk()->getContent();

    // A save closes the dialog and redraws the tree, and the library the link
    // picker opens is the one of the menu form under the dialog.
    expect($form)->toContain('modal_toggled:cms-menu-item-'.$item->getKey().',fragment_updated:'.MenuTreeView::FRAGMENT)
        ->and($form)->toContain('name="_without-redirect"')
        ->and($form)->not->toContain('data-cms-dialog=');

    $response = submitForm(app(MenuItemResource::class)->getRoute('crud.update', $item->getKey()), [
        '_method' => 'PUT',
        '_without-redirect' => '1',
        'label' => ['ru' => 'О компании'],
        'is_active' => '1',
    ])->assertOk();

    expect($response->json('redirect'))->toBeNull()
        ->and($item->refresh()->label)->toBe(['ru' => 'О компании']);
});

it('closes the dialog a new nested item was added in', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');

    $this->get(app(MenuItemResource::class)->getFormPageUrl(params: [
        'menu' => $menu->getKey(),
        'parent' => $root->getKey(),
        MenuItemFormPage::MODAL => 1,
        '_async_form' => 1,
    ], fragment: 'crud-form'))
        ->assertOk()
        ->assertSee('modal_toggled:cms-menu-item-new-'.$root->getKey().',fragment_updated:'.MenuTreeView::FRAGMENT, false)
        ->assertSee('name="cms_menu_id"', false);
});

it('nests an item under a root one on a drop and reorders siblings by position', function (): void {
    $menu = menu();
    $first = menuItem($menu, 'Первый');
    $second = menuItem($menu, 'Второй');
    $third = menuItem($menu, 'Третий');

    $this->postJson(moveMenuItemUrl($menu), ['item' => $third->getKey(), 'parent' => $first->getKey(), 'position' => null])
        ->assertOk()
        ->assertJsonPath('parent', $first->getKey());

    expect($third->refresh()->parent_id)->toBe($first->getKey())
        ->and($third->sort_order)->toBe(0);

    // Back to the root, in front of the first item.
    $this->postJson(moveMenuItemUrl($menu), ['item' => $third->getKey(), 'parent' => null, 'position' => 0])->assertOk();

    expect($third->refresh()->parent_id)->toBeNull()
        ->and([$third->sort_order, $first->refresh()->sort_order, $second->refresh()->sort_order])->toBe([0, 1, 2]);
});

it('refuses a drop that would make a third level and keeps the item where it was', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');
    $child = menuItem($menu, 'О компании', $root);
    $loose = menuItem($menu, 'Контакты');

    $this->postJson(moveMenuItemUrl($menu), ['item' => $loose->getKey(), 'parent' => $child->getKey()])
        ->assertStatus(422);

    expect($loose->refresh()->parent_id)->toBeNull();

    // An item that already has children cannot become a child itself.
    $this->postJson(moveMenuItemUrl($menu), ['item' => $root->getKey(), 'parent' => $loose->getKey()])
        ->assertStatus(422);

    expect($root->refresh()->parent_id)->toBeNull()
        ->and($child->refresh()->parent_id)->toBe($root->getKey());

    // Into itself, into another menu, and nothing to move at all.
    $other = CmsMenu::query()->create(['code' => 'footer_1', 'name' => ['ru' => 'Подвал'], 'source' => CmsMenu::SOURCE_MANUAL, 'is_active' => true]);
    $foreign = menuItem($other, 'Чужой');

    $this->postJson(moveMenuItemUrl($menu), ['item' => $root->getKey(), 'parent' => $root->getKey()])->assertStatus(422);
    $this->postJson(moveMenuItemUrl($menu), ['item' => 999, 'parent' => null])->assertStatus(404);
    $this->postJson(moveMenuItemUrl($menu), ['item' => $loose->getKey(), 'parent' => 999])->assertStatus(404);

    // An item of another menu is unknown to this form, parent or moved item
    // alike: the command never reaches past the menu the address names.
    $this->postJson(moveMenuItemUrl($menu), ['item' => $loose->getKey(), 'parent' => $foreign->getKey()])->assertStatus(404);
    $this->postJson(moveMenuItemUrl($menu), ['item' => $foreign->getKey(), 'parent' => null])->assertStatus(404);

    expect($loose->refresh()->parent_id)->toBeNull()
        ->and($foreign->refresh()->cms_menu_id)->toBe($other->getKey())
        ->and($foreign->sort_order)->toBe(1);
});

it('closes the group an item left so both groups stay dense', function (): void {
    $menu = menu();
    $first = menuItem($menu, 'Первый');
    $second = menuItem($menu, 'Второй');
    $third = menuItem($menu, 'Третий');

    // Dense to begin with: the drop of the last item to the top renumbers
    // the root group.
    $this->postJson(moveMenuItemUrl($menu), ['item' => $third->getKey(), 'parent' => null, 'position' => 0])->assertOk();

    expect([$third->refresh()->sort_order, $first->refresh()->sort_order, $second->refresh()->sort_order])
        ->toBe([0, 1, 2]);

    // Out of the root group and under a parent: what it left closes up.
    $this->postJson(moveMenuItemUrl($menu), ['item' => $first->getKey(), 'parent' => $third->getKey()])->assertOk();

    expect([$third->refresh()->sort_order, $second->refresh()->sort_order])->toBe([0, 1])
        ->and($first->refresh()->parent_id)->toBe($third->getKey())
        ->and($first->sort_order)->toBe(0);
});

it('answers the move command on a post only', function (): void {
    $menu = menu();
    $item = menuItem($menu, 'Информация');

    $this->get(moveMenuItemUrl($menu))->assertStatus(405);

    expect($item->refresh()->sort_order)->toBe(1);
});

it('sends the flat list of items back to the menus', function (): void {
    $this->get(app(MenuItemResource::class)->getIndexPageUrl())
        ->assertRedirect(menuResource()->getIndexPageUrl());
});

it('creates an item in the menu the address names, under the parent it names', function (): void {
    $menu = menu();
    $root = menuItem($menu, 'Информация');

    $form = app(MenuItemResource::class)->getFormPageUrl(params: [
        'menu' => $menu->getKey(),
        'parent' => $root->getKey(),
    ]);

    $this->get($form)
        ->assertOk()
        ->assertSee('name="cms_menu_id"', false)
        ->assertSee('Информация', false);

    submitForm(app(MenuItemResource::class)->getRoute('crud.store'), [
        'cms_menu_id' => (string) $menu->getKey(),
        'parent_id' => (string) $root->getKey(),
        'label' => ['ru' => 'О компании'],
        'is_active' => '1',
    ])->assertSuccessful();

    $created = CmsMenuItem::query()->where('parent_id', $root->getKey())->sole();

    expect($created->cms_menu_id)->toBe($menu->getKey());
});

it('asks where the menu is shown in words and writes the places it names', function (): void {
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Шапка'], 'is_active' => true, 'content' => []]);
    $footer = CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Подвал'], 'is_active' => true, 'content' => []]);
    $menu = menu();

    $this->get(menuResource()->getFormPageUrl($menu->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::menus.slots'), false)
        ->assertSee(__('moonshine-cms::menus.slot_labels.footer_1'), false)
        ->assertSee('name="slots[]"', false);

    submitForm(
        menuResource()->getRoute('crud.update', $menu->getKey()),
        [
            'name' => ['ru' => 'Меню'],
            'code' => $menu->code,
            'source' => CmsMenu::SOURCE_MANUAL,
            'is_active' => '1',
            'slots' => ['header_primary', 'legal'],
            SaveMenu::SUBMITTED => '1',
        ],
        'PUT',
    )->assertSuccessful();

    $placed = CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->getKey())->get();

    // A place says by itself whether it belongs to the header or to the
    // footer, so nobody is asked to pick an owner for it.
    expect($placed->pluck('slot')->all())->toBe(['header_primary', 'legal'])
        ->and($placed->firstWhere('slot', 'header_primary')?->owner_id)->toBe($header->getKey())
        ->and($placed->firstWhere('slot', 'legal')?->owner_id)->toBe($footer->getKey());

    // Unchecking a place removes the row and leaves the rest alone.
    submitForm(
        menuResource()->getRoute('crud.update', $menu->getKey()),
        [
            'name' => ['ru' => 'Меню'],
            'code' => $menu->code,
            'source' => CmsMenu::SOURCE_MANUAL,
            'is_active' => '1',
            'slots' => ['legal'],
            SaveMenu::SUBMITTED => '1',
        ],
        'PUT',
    )->assertSuccessful();

    expect(CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->getKey())->pluck('slot')->all())->toBe(['legal']);

    // Every place unchecked sends no value at all, and the mark of the form is
    // what tells that apart from a form that never asked.
    submitForm(
        menuResource()->getRoute('crud.update', $menu->getKey()),
        [
            'name' => ['ru' => 'Меню'],
            'code' => $menu->code,
            'source' => CmsMenu::SOURCE_MANUAL,
            'is_active' => '1',
            SaveMenu::SUBMITTED => '1',
        ],
        'PUT',
    )->assertSuccessful();

    expect(CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->getKey())->count())->toBe(0);
});

/**
 * The update of a menu as its form posts it, with the mark that the form asked
 * about the places.
 *
 * @param  list<string>  $slots
 */
function saveMenuSlots(CmsMenu $menu, array $slots): \Illuminate\Testing\TestResponse
{
    return submitForm(
        menuResource()->getRoute('crud.update', $menu->getKey()),
        [
            'name' => ['ru' => 'Меню'],
            'code' => $menu->code,
            'source' => CmsMenu::SOURCE_MANUAL,
            'is_active' => '1',
            'slots' => $slots,
            SaveMenu::SUBMITTED => '1',
        ],
        'PUT',
    );
}

it('offers a place the installation declared in its own words and hangs the menu on its owner', function (): void {
    $footer = CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Подвал'], 'is_active' => true, 'content' => []]);
    // A record of the installation that is neither a header nor a footer, the
    // way the footer of a letter is.
    $letter = Catalogue::query()->create(['name' => 'Письмо']);

    app(MenuSlotCatalog::class)->register(
        MenuSlotDefinition::make('mail_legal')
            ->label(static fn (): string => 'Письмо: юридические ссылки')
            ->ownedBy(static fn (): Catalogue => $letter),
    );

    $menu = menu();

    $this->get(menuResource()->getFormPageUrl($menu->getKey()))
        ->assertOk()
        ->assertSee('Письмо: юридические ссылки', false)
        ->assertSee('value="mail_legal"', false);

    saveMenuSlots($menu, ['mail_legal', 'legal'])->assertSuccessful();

    $placed = CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->getKey())->get();
    $mail = $placed->firstWhere('slot', 'mail_legal');
    $legal = $placed->firstWhere('slot', 'legal');

    // The declared place lands on its declared owner, the place of the footer
    // on the footer, each by its own rule.
    expect($placed->pluck('slot')->all())->toBe(['mail_legal', 'legal'])
        ->and($mail?->owner_type)->toBe($letter->getMorphClass())
        ->and($mail?->owner_id)->toBe($letter->getKey())
        ->and($legal?->owner_type)->toBe($footer->getMorphClass())
        ->and($legal?->owner_id)->toBe($footer->getKey());
});

it('puts nothing into a declared place whose owner does not exist yet', function (): void {
    app(MenuSlotCatalog::class)->register(
        MenuSlotDefinition::make('mail_legal')->ownedBy(static fn (): ?Catalogue => null),
    );

    $menu = menu();

    saveMenuSlots($menu, ['mail_legal'])->assertSuccessful();

    expect(CmsMenuSlotAssignment::query()->count())->toBe(0);
});

it('says there is nowhere to show a menu while a declared place has no owner yet', function (): void {
    $owner = null;

    app(MenuSlotCatalog::class)->register(
        MenuSlotDefinition::make('mail_legal')->ownedBy(static function () use (&$owner): ?Catalogue {
            return $owner;
        }),
    );

    $menu = menu();

    $this->get(menuResource()->getFormPageUrl($menu->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::menus.slots_no_chrome'), false)
        ->assertDontSee(__('moonshine-cms::menus.slots_hint'), false);

    // Once the owner exists there is a place, with no header or footer at all.
    $owner = Catalogue::query()->create(['name' => 'Письмо']);

    $this->get(menuResource()->getFormPageUrl($menu->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::menus.slots_hint'), false)
        ->assertDontSee(__('moonshine-cms::menus.slots_no_chrome'), false);
});

it('keeps a row in a place added by configuration when the menu is saved', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);
    $footer = CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Подвал'], 'is_active' => true, 'content' => []]);
    $menu = menu();

    // Put there from the form of the footer, the one place that can.
    $kept = $footer->menuSlots()->create(['slot' => 'support', 'cms_menu_id' => $menu->getKey(), 'sort_order' => 0, 'is_enabled' => true]);

    saveMenuSlots($menu, ['legal'])->assertSuccessful();
    saveMenuSlots($menu, [])->assertSuccessful();

    expect(CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->getKey())->pluck('id')->all())
        ->toBe([$kept->getKey()]);
});

it('refuses a place the form does not offer', function (): void {
    config()->set('cms-layouts.menu_slots', ['support']);
    CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Подвал'], 'is_active' => true, 'content' => []]);
    $menu = menu();

    // Such a place names no owner, so it is filled on the form of the footer.
    $this->get(menuResource()->getFormPageUrl($menu->getKey()))
        ->assertOk()
        ->assertDontSee('value="support"', false);

    saveMenuSlots($menu, ['support'])->assertStatus(422)->assertJsonValidationErrors(['slots.0']);
    // A place that is no place of the frontend at all.
    saveMenuSlots($menu, ['sidebar'])->assertStatus(422)->assertJsonValidationErrors(['slots.0']);

    expect(CmsMenuSlotAssignment::query()->count())->toBe(0);
});

it('builds the code of a menu from its name and never rewrites it on a rename', function (): void {
    $this->get(menuResource()->getFormPageUrl())
        ->assertOk()
        ->assertSee('data-cms-slug-target="code"', false)
        ->assertSee('data-cms-slug-source', false);

    submitForm(menuResource()->getRoute('crud.store'), [
        'name' => ['ru' => 'Юридические ссылки'],
        'source' => CmsMenu::SOURCE_MANUAL,
        'is_active' => '1',
    ])->assertSuccessful();

    $menu = CmsMenu::query()->sole();

    expect($menu->code)->toBe('yuridicheskie-ssylki');

    submitForm(
        menuResource()->getRoute('crud.update', $menu->getKey()),
        ['name' => ['ru' => 'Документы'], 'code' => $menu->code, 'source' => CmsMenu::SOURCE_MANUAL, 'is_active' => '1'],
        'PUT',
    )->assertSuccessful();

    expect($menu->refresh()->code)->toBe('yuridicheskie-ssylki');
});

it('asks nothing technical on the form of an item', function (): void {
    $menu = menu();
    $item = menuItem($menu, 'Информация');

    $this->get(app(MenuItemResource::class)->getFormPageUrl($item->getKey()))
        ->assertOk()
        // No order, no icon code, no variant code: none of them is a question
        // an editor can answer.
        ->assertDontSee('name="sort_order"', false)
        ->assertDontSee('name="icon"', false)
        ->assertDontSee('name="variant"', false)
        ->assertSee(__('moonshine-cms::menus.item_title_edit', ['label' => 'Информация']), false);
});
