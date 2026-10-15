<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;
use Ewk\MoonShineCms\Adapters\FieldAdapter;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\AssetRichText;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\EditorOwnerResource;
use Ewk\MoonShineCms\Tests\Fixtures\StaticLanguageCatalog;
use MoonShine\Crud\Exceptions\NotFoundException;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function formPage(object $owner): string
{
    return app(EditorOwnerResource::class)->getFormPageUrl($owner->getKey());
}

/**
 * How deep `<form>` elements nest in the document. One means every form stands
 * on its own; two means the bug this editor was rebuilt to fix.
 */
function deepestFormNesting(string $html): int
{
    $depth = 0;
    $deepest = 0;

    preg_match_all('#</?form\b#i', $html, $matches);

    foreach ($matches[0] as $tag) {
        if (str_starts_with($tag, '</')) {
            $depth = max(0, $depth - 1);

            continue;
        }

        $depth++;
        $deepest = max($deepest, $depth);
    }

    return $deepest;
}

it('builds the card of a block out of its schema and nothing else', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();

    // Inputs derived from the declaration: a translatable text, a number with
    // its bounds, a select of the declared options, a nested repeater.
    $response->assertSee('name="content[title][ru]"', false);
    $response->assertSee('name="content[weight]"', false);
    $response->assertSee('name="content[align]"', false);
    $response->assertSee('name="content[seo][slug]"', false);
    $response->assertSee('name="content[point][lat]"', false);
    $response->assertSee('name="content[cta][kind]"', false);

    // Nothing from another type leaks into the card.
    $response->assertDontSee('name="content[caption]"', false);
});

it('shows one language tab per locale of the catalog and not a hardcoded pair', function (): void {
    app()->instance(LanguageCatalog::class, new StaticLanguageCatalog(['de', 'fr'], 'de'));

    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee('name="content[title][de]"', false);
    $response->assertSee('name="content[title][fr]"', false);
    $response->assertDontSee('name="content[title][ru]"', false);
});

it('loads the assets of a rich text field on the page of the editor', function (): void {
    config()->set('moonshine-cms.rich_text_field', AssetRichText::class);
    app()->forgetInstance(FieldAdapter::class);

    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->get(blocksPage($owner));

    // The editor of a card is an ordinary MoonShine field, and its script
    // has to reach the head of the document. The cards are therefore built
    // before the layout renders, see \Ewk\MoonShineCms\MoonShine\Components\BlockEditor::assets().
    $response->assertOk();
    $response->assertSee(AssetRichText::ASSET, false);
});

it('draws the glyph of the type in the head of a card', function (): void {
    config()->set('moonshine-cms.block_icons', ['every_field' => 'photo']);

    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The same picture the type was picked by in the library.
    expect($html)->toContain('data-cms-icon="block-every_field"')
        ->and($html)->toContain('class="cms-card__icon"');
});

it('carries the stored values into the card', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, [
        'type' => 'text',
        'name' => 'Вступление',
        'anchor' => 'intro',
        'content' => ['title' => ['ru' => 'Заголовок из базы']],
    ]);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee('Заголовок из базы', false);
    $response->assertSee('Вступление', false);
});

it('draws an inserted shared block with a warning and without fields of its own', function (): void {
    $owner = editorOwner();
    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);

    cmsBlock($shared, ['type' => 'text', 'name' => 'Текст промо']);
    cmsBlock($owner, [
        'type' => 'shared',
        'name' => 'Промо',
        'cms_shared_block_id' => $shared->getKey(),
    ]);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee(__('moonshine-cms::editor.shared_warning'));
    $response->assertDontSee('name="content[title][ru]"', false);
});

it('labels the slots of a container and offers to fill them', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee('data-cms-slot="body"', false);
    $response->assertSee(__('moonshine-cms::editor.add_to_slot'));
});

it('offers a plus before the first card, between the cards and at the end', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['name' => 'Первый', 'sort_order' => 0]);
    cmsBlock($owner, ['name' => 'Второй', 'sort_order' => 1]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // Three gaps around two cards: insertion goes to an exact position, not
    // only to the end of the list.
    expect($html)->toContain('data-cms-insert-at="root-0"')
        ->and($html)->toContain('data-cms-insert-at="root-1"')
        ->and($html)->toContain('data-cms-insert-at="root-2"')
        ->and($html)->toContain(__('moonshine-cms::editor.insert_here'));
});

it('offers a plus at every position of a slot and none once the slot is full', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    cmsBlock($owner, [
        'name' => 'Внутри',
        'parent_id' => $panel->getKey(),
        'slot' => 'body',
        'sort_order' => 0,
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('data-cms-insert-at="'.$panel->getKey().'-body-0"')
        ->and($html)->toContain('data-cms-insert-at="'.$panel->getKey().'-body-1"');

    // The slot of the fixture holds two at most: filled up, it offers no
    // insertion at all, neither between the cards nor at the end.
    cmsBlock($owner, [
        'name' => 'Второй внутри',
        'parent_id' => $panel->getKey(),
        'slot' => 'body',
        'sort_order' => 1,
    ]);

    $full = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($full)->not->toContain('data-cms-insert-at="'.$panel->getKey().'-body-0"')
        ->and($full)->not->toContain(__('moonshine-cms::editor.add_to_slot'));
});

it('fetches the library instead of drawing the whole catalogue into the document', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['name' => 'Первый']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // What may be inserted depends on the slot and on the type of the owner,
    // and both are known when the modal opens, so the document carries the
    // modal and the address only.
    expect($html)->toContain('cmsBlockLibrary')
        ->and($html)->not->toContain('data-cms-category="layout"')
        ->and($html)->not->toContain('data-cms-entry=');
});

it('carries one save all button and names the form of every card', function (): void {
    $owner = editorOwner();
    $first = cmsBlock($owner, ['name' => 'Первый', 'sort_order' => 0]);
    $second = cmsBlock($owner, ['name' => 'Второй', 'sort_order' => 1]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('data-cms-save-all')
        ->and($html)->toContain(__('moonshine-cms::editor.save_all'))
        // Every card form is addressable on its own: «save all» walks them and
        // posts each to its own endpoint, so the optimistic lock of every card
        // keeps working.
        ->and($html)->toContain('data-cms-form="'.$first->getKey().'"')
        ->and($html)->toContain('data-cms-form="'.$second->getKey().'"')
        // The name of a card in a report is what its head shows, and the head
        // is read off the content and not off a typed name.
        ->and($html)->toContain('data-cms-name="'.__('cms-blocks::blocks.text.title').'"');
});

it('shows the publication indicator and the publish button', function (): void {
    $owner = editorOwner();
    cmsBlock($owner);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee('data-cms-status="draft"', false);
    $response->assertSee(__('moonshine-cms::editor.publish'));
});

it('says so when there is nothing to show yet', function (): void {
    $response = $this->get(blocksPage(editorOwner()));

    $response->assertOk();
    $response->assertSee(__('moonshine-cms::editor.empty'));
});

it('refuses the editor of an owner that does not exist', function (): void {
    $owner = editorOwner();
    $url = blocksPage($owner);
    $owner->delete();

    $this->withoutExceptionHandling();

    expect(fn () => $this->get($url))->toThrow(NotFoundException::class);
});

it('keeps the form of every card out of any other form', function (): void {
    // The whole point of the separate page: a card is a form, the resource
    // form was a form, and the browser silently dropped the first card's
    // opening tag — its fields joined the page form and lost the scope that
    // drives conditional inputs.
    $owner = editorOwner();
    $first = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Первый', 'sort_order' => 0]);
    $second = cmsBlock($owner, ['type' => 'text', 'name' => 'Второй', 'sort_order' => 1]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect(deepestFormNesting($html))->toBe(1)
        ->and($html)->toContain('data-component="cms-block-'.$first->getKey().'"')
        ->and($html)->toContain('data-component="cms-block-'.$second->getKey().'"');
});

it('draws the composite fields of a repeater row with the index of the row in their names', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, [
        'type' => 'slides',
        'name' => 'Слайдер',
        'content' => ['slides' => [
            [
                'title' => ['ru' => 'Первый'],
                'image' => ['id' => 'a9f3'],
                'link' => ['kind' => 'url', 'attributes' => ['url' => 'https://example.test'], 'new_tab' => true],
            ],
        ]],
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The placeholder is what the client rewrites into the number of the row;
    // without it the second slide would write over the first.
    expect($html)->toContain('name="content[slides][${index0}][link][kind]"')
        ->and($html)->toContain('data-name="content[slides][${index0}][link][kind]"')
        ->and($html)->toContain('name="content[slides][${index0}][image][id]"')
        // The link names its own inputs after the one the client renames, so
        // the row carries the placeholder once and not once per target.
        ->and($html)->toContain("base: 'content[slides][\${index0}][link]'")
        // the stored value reaches the input of the row
        ->and($html)->toContain('https://example.test');
});

it('hides the caption of a repeater row together with the language it belongs to', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, [
        'type' => 'slides',
        'name' => 'Слайдер',
        'content' => ['slides' => [['title' => ['ru' => 'Первый']]]],
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // MoonShine draws the caption of a vertical repeater row in a column of
    // its own, outside the wrapper the adapter marks with a language: the
    // caption of a hidden language used to stay on the screen with an empty
    // place under it, and the row read as one field too many.
    expect($html)->toContain('data-cms-locale-label="ru"')
        ->and($html)->toContain('data-cms-locale-label="en"')
        ->and(substr_count($html, 'data-cms-locale-label="en"'))
        ->toBe(substr_count($html, 'data-cms-locale="en"'))
        // The caption of the language that is not shown starts hidden, just
        // like the input under it.
        ->and($html)->toContain('style="display:none;" data-cms-locale-label="en"')
        // The condition names no language of its own: an attribute escaped
        // twice would arrive as `locale === &#039;en&#039;` and stop being
        // JavaScript. The guard in front of it is a ternary over a `typeof`
        // for the same reason: an `&&` comes back as `&amp;&amp;`.
        ->and($html)->toContain('x-show="typeof locale === typeof undefined ? false : locale === $el.dataset.cmsLocale"')
        ->and($html)->toContain('x-show="typeof locale === typeof undefined ? false : locale === $el.dataset.cmsLocaleLabel"')
        ->and($html)->not->toContain('locale === &amp;#039;')
        ->and($html)->not->toContain('typeof undefined &amp;&amp;');
});

it('offers a page of the tree instead of a uuid to type in', function (): void {
    app(PageTypeRegistry::class)->register(
        PageType::make('tour')
            ->title('Тур')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Catalogue::class)->visible("query"))
            ->path('/tours/{tour}'),
    );

    CmsPage::query()->create(['type' => 'page', 'name' => ['ru' => 'Контакты'], 'slug' => 'contacts', 'path' => '/contacts', 'is_active' => true]);
    CmsPage::query()->create(['type' => 'page', 'name' => ['ru' => 'Черновик'], 'slug' => 'draft', 'path' => '/draft', 'is_active' => false]);
    CmsPage::query()->create(['type' => 'tour', 'name' => ['ru' => 'Шаблон тура'], 'slug' => 'tour', 'path' => '/tours/{tour}', 'is_active' => true]);

    $contacts = CmsPage::query()->where('slug', 'contacts')->firstOrFail();
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'slides', 'name' => 'Слайдер', 'content' => ['slides' => [[
        'title' => ['ru' => 'Первый'],
        'link' => ['kind' => 'page', 'attributes' => ['page' => $contacts->uuid], 'new_tab' => false],
    ]]]]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The pages are searched over the wire, so the document carries the
    // address of the lookup and not the tree, and the caption of the chosen
    // page instead of its uuid.
    expect(plainUrls($html))->toContain('cms/link/pages')
        ->and($html)->toContain('Контакты (/contacts)')
        ->and($html)->not->toContain('Черновик (/draft)')
        ->and($html)->not->toContain('Шаблон тура');
});

it('draws the pagination settings the schema hands to the editor and keeps the generated anchor visible', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'feed', 'name' => 'Лента']);

    // A dynamic block gets its anchor at save time; the card has to show it.
    expect($block->anchor)->toBe('feed');

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertSee('name="content[per_page]"', false);
    $response->assertSee('name="content[pagination_mode]"', false);
    // Only the two modes the schema allows.
    $response->assertSee('value="pages"', false);
    $response->assertSee('value="more"', false);
    $response->assertDontSee('value="none"', false);
    $response->assertSee('value="feed"', false);
});

it('draws no pagination settings for a block whose schema keeps them to itself', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->get(blocksPage($owner));

    $response->assertOk();
    $response->assertDontSee('name="content[per_page]"', false);
    $response->assertDontSee('name="content[pagination_mode]"', false);
});

it('reads the caption of a card out of the content and never out of a typed name', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, [
        'type' => 'every_field',
        'name' => 'Служебное имя',
        'content' => ['title' => ['ru' => 'Rammstein в Берлине']],
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // «Type: what the block says». The type is not repeated as the caption the
    // way a stored name repeated it.
    expect($html)->toContain('class="cms-card__type">Все поля<')
        ->and($html)->toContain('Rammstein в Берлине');
});

it('shows the type alone while the block says nothing yet', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Служебное имя', 'content' => []]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('class="cms-card__type">Все поля<')
        ->and($html)->not->toContain('class="cms-card__caption');
});

it('keeps the fields of a card in the order the schema declares them', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    $title = strpos($html, 'name="content[title][ru]"');
    $weight = strpos($html, 'name="content[weight]"');
    $align = strpos($html, 'name="content[align]"');

    // A translatable field no longer sinks into a tab strip at the end: it
    // stands where the declaration put it.
    expect($title)->toBeLessThan($weight)
        ->and($weight)->toBeLessThan($align)
        // Every language is drawn, and the switcher of the panel decides
        // which one is visible.
        ->and($html)->toContain('data-cms-locale="ru"')
        ->and($html)->toContain('data-cms-locale="en"')
        ->and($html)->toContain('name="content[title][en]"');
});

it('folds the name, the anchor and the activity of a block away', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'text', 'name' => 'Текст']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain(__('moonshine-cms::editor.more'))
        ->and($html)->toContain('cms-card__more"')
        ->and($html)->toContain('name="anchor"');
});

it('draws the slots of a container as a grid with a list of its own', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // A slot is a column of a twelve column grid and a drop target of the
    // sortable at the same time.
    expect($html)->toContain('--cms-slot-span: 12')
        ->and($html)->toContain('data-id="'.$panel->getKey().'"')
        ->and($html)->toContain('data-slot="body"');
});

it('carries one language switch for the whole editor', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('data-cms-locale-switch="ru"')
        ->and($html)->toContain('data-cms-locale-switch="en"');
});

it('draws one handle per card and no drop strips between them', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['name' => 'Первый']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect(substr_count($html, 'class="cms-card__handle"'))->toBe(1)
        ->and($html)->not->toContain('cms-dropzone')
        // The root list is the drop target of the sortable.
        ->and($html)->toContain('data-cms-container');
});

it('keeps the hint of the owner inside the panel of the editor', function (): void {
    $owner = editorOwner();

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The empty state and the hints live in the panel and not above the
    // «settings | blocks» switch.
    $panel = strpos($html, 'class="cms-editor__panel"');
    $empty = strpos($html, __('moonshine-cms::editor.empty'));

    expect($panel)->toBeLessThan($empty);
});
