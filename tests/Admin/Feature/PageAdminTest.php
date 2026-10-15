<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageBlocksPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function storePageUrl(): string
{
    return pageResource()->getRoute('crud.store');
}

function updatePageUrl(CmsPage $page): string
{
    return pageResource()->getRoute('crud.update', $page->getKey());
}

it('creates a page with a translatable caption and derives its address from the slug', function (): void {
    $response = submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия', 'en' => 'Terms', 'de' => ''],
        'slug' => 'agb',
        'type' => 'page',
        'is_active' => '1',
    ]);

    $response->assertSuccessful();

    $page = CmsPage::query()->where('slug', 'agb')->firstOrFail();

    // The address is derived from the slug by the tree, not typed in; an
    // empty translation is dropped so that it falls back instead of
    // rendering as nothing.
    expect($page->path)->toBe('/agb')
        ->and($page->depth)->toBe(0)
        ->and($page->name)->toBe(['ru' => 'Условия', 'en' => 'Terms']);
});

it('requires the caption in the default language only', function (): void {
    submitForm(storePageUrl(), [
        'name' => ['en' => 'Terms'],
        'slug' => 'agb',
        'type' => 'page',
    ])->assertStatus(422)->assertJsonValidationErrors(['name.ru']);

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия'],
        'slug' => 'agb',
        'type' => 'page',
    ])->assertSuccessful();
});

it('normalizes the slug typed by the editor instead of rejecting it', function (string $typed, string $stored): void {
    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия использования'],
        'slug' => $typed,
        'type' => 'page',
        'is_active' => '1',
    ])->assertSuccessful();

    $this->assertDatabaseHas('cms_pages', ['slug' => $stored, 'path' => '/'.$stored]);
})->with([
    'leading slash' => ['/agb', 'agb'],
    'trailing slash' => ['agb/', 'agb'],
    'a caption pasted in' => ['Условия сервиса', 'usloviya-servisa'],
    // A slash inside is a word boundary: nesting lives in the tree.
    'slash inside' => ['help/faq', 'help-faq'],
    'empty: from the caption' => ['', 'usloviya-ispolzovaniya'],
    'only a slash: from the caption' => ['/', 'usloviya-ispolzovaniya'],
]);

it('creates a template page at the address of its declaration', function (): void {
    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Страница концерта'],
        'slug' => 'concert-template',
        'type' => 'concert',
        'is_active' => '1',
    ])->assertSuccessful();

    $page = CmsPage::query()->where('slug', 'concert-template')->firstOrFail();

    expect($page->type)->toBe('concert')
        ->and($page->path)->toBe('/concerts/{concert}');
});

it('rejects a second template of the same type, active or draft: the address is one', function (): void {
    cmsPage(['type' => 'concert', 'slug' => 'concert-template']);

    foreach (['1', '0'] as $active) {
        submitForm(storePageUrl(), [
            'name' => ['ru' => 'Ещё один шаблон'],
            'slug' => 'concert-template-'.$active,
            'type' => 'concert',
            'is_active' => $active,
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    expect(CmsPage::query()->where('type', 'concert')->count())->toBe(1);
});

it('creates the singleton once and rejects a second one', function (): void {
    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Главная'],
        'slug' => 'home',
        'type' => 'home',
        'is_active' => '1',
    ])->assertSuccessful();

    expect(CmsPage::query()->where('type', 'home')->sole()->path)->toBe('/');

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Вторая главная'],
        'slug' => 'home-2',
        'type' => 'home',
        'is_active' => '1',
    ])->assertStatus(422)->assertJsonValidationErrors(['type']);

    expect(CmsPage::query()->where('type', 'home')->count())->toBe(1);
});

it('refuses to delete a singleton and a template, deletes an ordinary page', function (): void {
    $home = cmsPage(['type' => 'home', 'slug' => 'home']);
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);
    $ordinary = cmsPage(['slug' => 'agb']);

    foreach ([$home, $template] as $protected) {
        $this->delete(pageResource()->getRoute('crud.destroy', $protected->getKey()));

        expect(CmsPage::query()->whereKey($protected->getKey())->exists())->toBeTrue();
    }

    $this->delete(pageResource()->getRoute('crud.destroy', $ordinary->getKey()));

    expect(CmsPage::query()->whereKey($ordinary->getKey())->exists())->toBeFalse();
});

it('keeps the type of a page whose address is declared', function (): void {
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);

    submitForm(updatePageUrl($template), [
        'name' => ['ru' => 'Страница концерта'],
        'slug' => 'concert-template',
        'type' => 'page',
        'is_active' => '1',
    ], 'PUT')->assertStatus(422)->assertJsonValidationErrors(['type']);

    expect($template->refresh()->type)->toBe('concert');
});

it('renames the address of a page and of its subtree with the slug', function (): void {
    $info = cmsPage(['slug' => 'info']);
    $help = cmsPage(['slug' => 'help'], $info);

    submitForm(updatePageUrl($info), [
        'name' => ['ru' => 'Информация'],
        'slug' => 'about',
        'type' => 'page',
        'is_active' => '1',
    ], 'PUT')->assertSuccessful();

    expect($info->refresh()->path)->toBe('/about')
        ->and($help->refresh()->path)->toBe('/about/help');
});

it('offers the active layouts by their caption and stores the chosen one', function (): void {
    CmsLayout::query()->create(['code' => 'wide', 'name' => ['ru' => 'Широкий'], 'is_active' => true]);
    CmsLayout::query()->create(['code' => 'off', 'name' => ['ru' => 'Выключен'], 'is_active' => false]);

    // The caption and never the code next to it: the code is the contract with
    // the frontend and says nothing to the editor choosing an appearance.
    $this->get(pageResource()->getFormPageUrl())
        ->assertOk()
        ->assertSee('Широкий', false)
        ->assertDontSee('Широкий (wide)', false)
        ->assertDontSee('Выключен', false);

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия'],
        'slug' => 'agb',
        'type' => 'page',
        'layout_code' => 'off',
    ])->assertStatus(422)->assertJsonValidationErrors(['layout_code']);

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия'],
        'slug' => 'agb',
        'type' => 'page',
        'layout_code' => 'wide',
    ])->assertSuccessful();

    expect(CmsPage::query()->where('slug', 'agb')->sole()->layout_code)->toBe('wide');
});

it('links the form of a saved page to its block editor and shows nothing of it on the create form', function (): void {
    $page = cmsPage(['slug' => 'home']);

    $this->get(pageResource()->getFormPageUrl($page->getKey()))
        ->assertOk()
        ->assertSee(pageResource()->blockEditorPageUrl($page->getKey()), false)
        ->assertDontSee(__('moonshine-cms::editor.publish'), false);

    $this->get(pageResource()->getFormPageUrl())
        ->assertOk()
        ->assertDontSee(__('moonshine-cms::editor.title'), false);
});

it('renders the block editor of a page on a page of its own', function (): void {
    $page = cmsPage(['slug' => 'home']);
    $block = cmsBlock($page, ['content' => ['title' => ['ru' => 'Условия'], 'body' => ['ru' => 'Текст']]]);

    $response = $this->get(pageResource()->blockEditorPageUrl($page->getKey()));

    $response->assertOk();
    $response->assertSee('name="content[title][ru]"', false);
    $response->assertSee('data-component="cms-block-'.$block->getKey().'"', false);
    $response->assertSee(__('moonshine-cms::editor.publish'), false);

    expect(pageResource()->getPages()->first(static fn ($p): bool => $p instanceof PageBlocksPage))->not->toBeNull();
});

it('reports a path colliding with a route of the frontend on the slug field instead of failing', function (): void {
    config()->set('cms-pages.reserved_segments', ['dashboard', 'checkout']);

    $response = submitForm(storePageUrl(), [
        'name' => ['ru' => 'Кабинет'],
        'slug' => 'dashboard',
        'type' => 'page',
        'is_active' => '1',
    ]);

    // A physical route of the frontend always wins, so this is a wrong value in
    // the form and never a failure of the panel.
    $response->assertStatus(422)->assertJsonValidationErrors(['slug']);

    expect(CmsPage::query()->where('slug', 'dashboard')->exists())->toBeFalse();
});

it('reports a path already taken by another page on the slug field', function (): void {
    cmsPage(['slug' => 'agb']);

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Условия'],
        'slug' => 'agb',
        'type' => 'page',
        'is_active' => '1',
    ])->assertStatus(422)->assertJsonValidationErrors(['slug']);

    expect(CmsPage::query()->where('slug', 'agb')->count())->toBe(1);
});

it('reports a rename onto a reserved path on the slug field and keeps the old address', function (): void {
    config()->set('cms-pages.reserved_segments', ['dashboard']);

    $page = cmsPage(['slug' => 'about']);

    submitForm(updatePageUrl($page), [
        'name' => ['ru' => 'О нас'],
        'slug' => 'dashboard',
        'type' => 'page',
        'is_active' => '1',
    ], 'PUT')->assertStatus(422)->assertJsonValidationErrors(['slug']);

    expect($page->fresh()?->path)->toBe('/about');
});

it('lays the form out in two tabs and opens the first one with the type of the page', function (): void {
    $page = cmsPage(['slug' => 'offer', 'name' => ['ru' => 'Публичная оферта']]);

    $response = $this->get(pageResource()->getFormPageUrl($page->getKey()))->assertOk();

    $response->assertSee(__('moonshine-cms::pages.tab_main'), false)
        ->assertSee(__('moonshine-cms::seo.tab'), false);

    $html = (string) $response->getContent();

    // The type is the first question of the form and not a tab of its own:
    // everything under it reads differently depending on the answer, so it
    // stands in the first tab, ahead of the metadata.
    expect(strpos($html, (string) __('moonshine-cms::pages.type')))
        ->toBeLessThan(strpos($html, (string) __('moonshine-cms::fields.slug')));
});

it('names the page in the title instead of the bare «edit»', function (): void {
    $page = cmsPage(['slug' => 'offer', 'name' => ['ru' => 'Публичная оферта']]);

    $this->get(pageResource()->getFormPageUrl($page->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::pages.title_edit', ['name' => 'Публичная оферта']), false);

    $this->get(pageResource()->getFormPageUrl())
        ->assertOk()
        ->assertSee(__('moonshine-cms::pages.title_new'), false);
});

it('seeds the address from the caption while the page is created and never on a rename', function (): void {
    // The form says so to the browser as well: the caption of the default
    // language feeds the address field while the page has no key yet.
    $this->get(pageResource()->getFormPageUrl())
        ->assertOk()
        ->assertSee('data-cms-slug-source', false)
        ->assertSee('data-cms-slug-target="slug"', false)
        ->assertSee('slugAuto: true', false);

    submitForm(storePageUrl(), [
        'name' => ['ru' => 'Возврат билетов'],
        'type' => 'page',
        'is_active' => '1',
    ])->assertSuccessful();

    $page = CmsPage::query()->sole();

    expect($page->slug)->toBe('vozvrat-biletov');

    // A rename leaves the address alone: the links already point at it.
    submitForm(
        pageResource()->getRoute('crud.update', $page->getKey()),
        ['name' => ['ru' => 'Возврат заказов'], 'slug' => $page->slug, 'type' => 'page', 'is_active' => '1'],
        'PUT',
    )->assertSuccessful();

    expect($page->refresh()->slug)->toBe('vozvrat-biletov');

    $this->get(pageResource()->getFormPageUrl($page->getKey()))
        ->assertOk()
        ->assertSee('slugAuto: false', false);
});

it('shows the metadata of one language at a time with a single snippet', function (): void {
    $page = cmsPage(['slug' => 'offer', 'name' => ['ru' => 'Оферта']]);

    $html = (string) $this->get(pageResource()->getFormPageUrl($page->getKey()))->assertOk()->getContent();

    // One set of inputs per language, shown by the switch of the form, and one
    // preview: the pairs of fields with «RU» and «EN» captions are gone.
    expect(substr_count($html, 'class="cms-seo__preview"'))->toBe(count(app(\Ewk\Cms\Media\Localization\LanguageCatalog::class)->locales()))
        ->and($html)->toContain('data-cms-locale-switch="en"')
        ->and($html)->toContain('name="seo[title][en]"')
        ->and($html)->toContain('cms-seo__counter');
});
