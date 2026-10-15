<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\SharedBlockBlocksPage;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function sharedResource(): SharedBlockResource
{
    return app(SharedBlockResource::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function sharedBlock(array $attributes = []): CmsSharedBlock
{
    return CmsSharedBlock::query()->create($attributes + [
        'code' => 'promo-10-percent',
        'name' => 'Промо 10 процентов',
        'is_active' => true,
    ]);
}

function sharedBlocksPageInstance(): SharedBlockBlocksPage
{
    foreach (sharedResource()->getPages() as $page) {
        if ($page instanceof SharedBlockBlocksPage) {
            return $page;
        }
    }

    throw new RuntimeException('The shared block resource registers no block editor page.');
}

/**
 * The url of one editor command of a library entry, exactly as the rendered
 * card posts to it.
 */
function sharedEditorUrl(string $method, CmsSharedBlock $sharedBlock): string
{
    return sharedResource()->getAsyncMethodUrl(
        $method,
        params: ['resourceItem' => $sharedBlock->getKey()],
        page: sharedBlocksPageInstance(),
    );
}

/**
 * An insertion of a library entry into an owner, the row the editor creates.
 */
function sharedInsertion(CmsSharedBlock $sharedBlock, \Illuminate\Database\Eloquent\Model $owner): CmsBlock
{
    return cmsBlock($owner, [
        'type' => 'shared',
        'cms_shared_block_id' => $sharedBlock->getKey(),
        'name' => $sharedBlock->name,
    ]);
}

it('lists the library flat and searches it by name', function (): void {
    sharedBlock(['code' => 'promo-banner', 'name' => 'Баннер акции']);
    sharedBlock(['code' => 'contacts-note', 'name' => 'Плашка контактов']);

    $rows = (string) indexRows(sharedResource())->assertOk()->getContent();

    expect($rows)->toContain('Баннер акции')->toContain('Плашка контактов');

    $index = sharedResource()->getIndexPage();
    $url = sharedResource()->getCore()->getRouter()->getEndpoints()->component(
        name: $index?->getListComponentName() ?? '',
        additionally: [
            '_fragment-load' => 'crud-list',
            'pageUri' => $index?->getUriKey(),
            'resourceUri' => sharedResource()->getUriKey(),
            'search' => 'Баннер',
        ],
    );

    $found = (string) $this->get($url)->assertOk()->getContent();

    expect($found)->toContain('Баннер акции')->not->toContain('Плашка контактов');
});

it('creates a library entry from the form and refuses a code that is not a slug', function (): void {
    $store = sharedResource()->getRoute('crud.store');

    submitForm($store, ['name' => 'Баннер акции', 'code' => 'Баннер Акции', 'is_active' => '1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    submitForm($store, ['name' => 'Баннер акции', 'code' => 'promo-banner', 'is_active' => '1'])
        ->assertSuccessful();

    $created = CmsSharedBlock::query()->where('code', 'promo-banner')->sole();

    expect($created->name)->toBe('Баннер акции')
        ->and($created->uuid)->not->toBeEmpty();

    // The code is unique across the library.
    submitForm($store, ['name' => 'Другой', 'code' => 'promo-banner', 'is_active' => '1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('opens the block editor of a library entry with the switcher and the publish button', function (): void {
    $sharedBlock = sharedBlock();
    cmsBlock($sharedBlock, ['name' => 'Текст промо']);

    $editor = sharedResource()->blockEditorPageUrl($sharedBlock->getKey());

    $this->get($editor)
        ->assertOk()
        ->assertSee('Текст промо', false)
        // The page renders the publish endpoint of the owner and the way
        // back to its settings.
        ->assertSee('method=publishCmsOwner', false)
        ->assertSee(sharedResource()->getFormPageUrl($sharedBlock->getKey()), false);

    // The form of the entry links back to the editor.
    $this->get(sharedResource()->getFormPageUrl($sharedBlock->getKey()))
        ->assertOk()
        ->assertSee($editor, false);
});

it('publishes a library entry through the owner it is', function (): void {
    $sharedBlock = sharedBlock();
    cmsBlock($sharedBlock, ['name' => 'Текст промо']);

    $this->post(sharedEditorUrl('publishCmsOwner', $sharedBlock))
        ->assertOk()
        ->assertJsonPath('number', 1);

    $version = CmsVersion::query()
        ->where('owner_type', $sharedBlock->getMorphClass())
        ->where('owner_id', $sharedBlock->getKey())
        ->sole();

    expect($version->number)->toBe(1)
        ->and($version->snapshot['blocks'][0]['type'] ?? null)->toBe('text');
});

it('offers shareable types only and never the library inside itself', function (): void {
    $sharedBlock = sharedBlock();

    $html = $this->get(sharedEditorUrl('cmsBlockLibrary', $sharedBlock))->assertOk()->getContent() ?: '';

    $codes = libraryEntryCodes($html);

    expect(libraryCategoryCodes($html))->not->toContain('shared')
        ->and($codes)->toContain('text')
        ->and($codes)->not->toContain('schedule')
        ->and($codes)->not->toContain('panel')
        ->and($codes)->not->toContain('slides');
});

it('refuses to insert a type the schema keeps out of the library', function (): void {
    $sharedBlock = sharedBlock();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'slides'])
        ->assertStatus(422)
        ->assertJsonPath('message', __('moonshine-cms::shared.not_shareable'));

    expect($sharedBlock->blocks()->count())->toBe(0);
});

it('keeps exactly one root block in a library entry', function (): void {
    $sharedBlock = sharedBlock();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'text'])->assertOk();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'text'])
        ->assertStatus(422)
        ->assertJsonPath('message', __('moonshine-cms::shared.single_root'));

    expect($sharedBlock->blocks()->whereNull('parent_id')->count())->toBe(1);
});

it('offers the types a slot accepts inside the entry, shareable or not', function (): void {
    $sharedBlock = sharedBlock();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'columns'])->assertOk();

    $container = $sharedBlock->blocks()->whereNull('parent_id')->sole();

    $html = $this->get(sharedEditorUrl('cmsBlockLibrary', $sharedBlock).'&parent='.$container->getKey().'&slot=col_1')
        ->assertOk()
        ->getContent() ?: '';

    // A shared block inside a shared block stays forbidden at every level.
    // «May be put into the library» describes the entry itself, so a nested
    // slot is governed by the slot and offers a type that is not shareable.
    expect(libraryCategoryCodes($html))->not->toContain('shared')
        ->and(libraryEntryCodes($html))->toContain('slides')->toContain('text');

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), [
        'code' => 'slides',
        'parent' => $container->getKey(),
        'slot' => 'col_1',
    ])->assertOk();

    expect($sharedBlock->blocks()->where('type', 'slides')->count())->toBe(1);
});

it('offers nothing at the root of an entry that already holds its root block', function (): void {
    $sharedBlock = sharedBlock();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'text'])->assertOk();

    $html = $this->get(sharedEditorUrl('cmsBlockLibrary', $sharedBlock))->assertOk()->getContent() ?: '';

    expect(libraryEntryCodes($html))->toBe([])
        ->and($html)->toContain(__('moonshine-cms::editor.library_nothing_allowed'));
});

it('refuses a drop that would give the entry a second root block', function (): void {
    $sharedBlock = sharedBlock();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), ['code' => 'columns'])->assertOk();

    $container = $sharedBlock->blocks()->whereNull('parent_id')->sole();

    $this->post(sharedEditorUrl('insertCmsBlock', $sharedBlock), [
        'code' => 'text',
        'parent' => $container->getKey(),
        'slot' => 'col_1',
    ])->assertOk();

    $child = $sharedBlock->blocks()->where('type', 'text')->sole();

    $this->post(sharedEditorUrl('moveCmsBlock', $sharedBlock), ['block' => $child->getKey(), 'parent' => null])
        ->assertStatus(422)
        ->assertJsonPath('message', __('moonshine-cms::shared.single_root'));

    expect($child->refresh()->parent_id)->toBe($container->getKey());

    // The root block itself still reorders where it stands.
    $this->post(sharedEditorUrl('moveCmsBlock', $sharedBlock), [
        'block' => $container->getKey(),
        'parent' => null,
        'position' => 0,
    ])->assertOk();
});

it('says on the editor of an entry that it holds one root block', function (): void {
    $sharedBlock = sharedBlock();

    $this->get(sharedResource()->blockEditorPageUrl($sharedBlock->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::shared.editor_hint'), false);
});

it('shows where the entry is inserted and links a page owner to its editor', function (): void {
    $sharedBlock = sharedBlock();
    $page = cmsPage(['slug' => 'info', 'name' => ['ru' => 'Информация']]);
    sharedInsertion($sharedBlock, $page);

    $this->get(sharedResource()->getFormPageUrl($sharedBlock->getKey()))
        ->assertOk()
        ->assertSee('Информация', false)
        ->assertSee('/info', false)
        ->assertSee(pageResource()->blockEditorPageUrl($page->getKey()), false)
        // The standard table of the panel and no markup of its own: the owner,
        // when it changed last and the way in.
        ->assertSee('data-cms-shared-usages-table', false)
        ->assertSee(__('moonshine-cms::shared.usage_owner'), false)
        ->assertSee(__('moonshine-cms::shared.usage_updated'), false)
        ->assertSee(__('moonshine-cms::shared.usage_open'), false)
        ->assertSee(__('moonshine-cms::shared.unlink_all'), false);
});

it('shows when an owner changed on the clock of the panel, not of the database', function (): void {
    config()->set('moonshine-media.display_timezone', 'Europe/Berlin');

    $sharedBlock = sharedBlock();
    $page = cmsPage(['slug' => 'info', 'name' => ['ru' => 'Информация']]);
    sharedInsertion($sharedBlock, $page);
    // 22:30 UTC in summer is 00:30 of the next day in Berlin.
    $page->forceFill(['updated_at' => CarbonImmutable::parse('2026-07-14 22:30:00', 'UTC')])->saveQuietly();

    $this->get(sharedResource()->getFormPageUrl($sharedBlock->getKey()))
        ->assertOk()
        ->assertSee('15.07.2026 00:30', false)
        ->assertDontSee('14.07.2026 22:30', false);
});

it('builds the code of a library entry from its name and keeps it on a rename', function (): void {
    $store = sharedResource()->getRoute('crud.store');

    $this->get(sharedResource()->getFormPageUrl())
        ->assertOk()
        ->assertSee('data-cms-slug-target="code"', false)
        ->assertSee('data-cms-slug-source', false);

    submitForm($store, ['name' => 'Баннер акции', 'is_active' => '1'])->assertSuccessful();

    $created = CmsSharedBlock::query()->sole();

    expect($created->code)->toBe('banner-aktsii');

    submitForm(
        sharedResource()->getRoute('crud.update', $created->getKey()),
        ['name' => 'Плашка акции', 'code' => $created->code, 'is_active' => '1'],
        'PUT',
    )->assertSuccessful();

    expect($created->refresh()->code)->toBe('banner-aktsii');
});

it('says the entry is used nowhere while nothing points at it', function (): void {
    $sharedBlock = sharedBlock();

    $this->get(sharedResource()->getFormPageUrl($sharedBlock->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::shared.usages_empty'), false)
        ->assertDontSee(__('moonshine-cms::shared.unlink_all'), false);
});

it('refuses to delete an entry in use, unlinks every insertion and deletes it then', function (): void {
    $sharedBlock = sharedBlock();
    $owner = editorOwner();
    $page = cmsPage(['slug' => 'info']);
    $kept = cmsBlock($page, ['name' => 'Обычный текст', 'sort_order' => 1]);
    sharedInsertion($sharedBlock, $page);
    sharedInsertion($sharedBlock, $owner);

    $this->delete(sharedResource()->getRoute('crud.destroy', $sharedBlock->getKey()));

    expect(CmsSharedBlock::query()->whereKey($sharedBlock->getKey())->exists())->toBeTrue();

    $unlink = sharedResource()->getAsyncMethodUrl(
        'unlinkInsertions',
        params: ['resourceItem' => $sharedBlock->getKey()],
        page: sharedResource()->getFormPage(),
    );

    // Unlinking rewrites rows, so the address does nothing on a GET: it is
    // sent by a button of the panel, with the token, and never by an address
    // a page somewhere manages to load.
    $this->get($unlink)->assertStatus(405);

    expect($sharedBlock->insertions()->count())->toBe(2);

    $this->post($unlink)
        ->assertOk()
        ->assertJsonPath('redirect', sharedResource()->getFormPageUrl($sharedBlock->getKey()));

    expect($sharedBlock->insertions()->count())->toBe(0)
        // Unlinking removes the insertion rows and nothing else.
        ->and(CmsBlock::query()->whereKey($kept->getKey())->exists())->toBeTrue()
        ->and(CmsSharedBlock::query()->whereKey($sharedBlock->getKey())->exists())->toBeTrue();

    $this->delete(sharedResource()->getRoute('crud.destroy', $sharedBlock->getKey()));

    expect(CmsSharedBlock::query()->whereKey($sharedBlock->getKey())->exists())->toBeFalse();
});
