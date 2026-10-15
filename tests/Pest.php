<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Tests\TestCase as BlocksTestCase;
use Ewk\Cms\Layouts\Tests\TestCase as LayoutsTestCase;
use Ewk\Cms\Media\MoonShine\Library\PickerModal;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\TestCase as PagesTestCase;
use Ewk\Cms\Seo\Tests\TestCase as SeoTestCase;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageBlocksPage;
use Ewk\MoonShineCms\Support\BlockVersion;
use Ewk\MoonShineCms\Tests\Fixtures\EditorOwner;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\EditorOwnerBlocksPage;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\EditorOwnerResource;
use Ewk\MoonShineCms\Tests\TestCase as AdminTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/*
 * Every part of the package keeps the base case it was written against: the
 * core suites boot only the providers their part stands on, the admin boots
 * the panel. The architecture rules in `Arch` need no application at all.
 */
uses(BlocksTestCase::class)->in('Blocks');
uses(PagesTestCase::class)->in('Pages');
uses(LayoutsTestCase::class)->in('Layouts');
uses(SeoTestCase::class)->in('Seo');
uses(AdminTestCase::class)->in('Admin');

/*
 * Helpers of the admin suite.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function editorOwner(array $attributes = []): EditorOwner
{
    return EditorOwner::query()->create($attributes + ['name' => 'Home']);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmsBlock(Model $owner, array $attributes = []): CmsBlock
{
    return CmsBlock::query()->create($attributes + [
        'blockable_type' => $owner->getMorphClass(),
        'blockable_id' => $owner->getKey(),
        'type' => 'text',
        'name' => 'Текст',
        'content' => [],
        'sort_order' => 0,
        'is_active' => true,
    ]);
}

/**
 * The url of the block editor page of one owner.
 */
function blocksPage(EditorOwner $owner): string
{
    return app(EditorOwnerResource::class)->blockEditorPageUrl($owner->getKey());
}

function blocksPageInstance(): EditorOwnerBlocksPage
{
    foreach (app(EditorOwnerResource::class)->getPages() as $page) {
        if ($page instanceof EditorOwnerBlocksPage) {
            return $page;
        }
    }

    throw new RuntimeException('The test resource registers no block editor page.');
}

/**
 * The url of one editor command, exactly as the rendered card posts to it.
 */
function editorUrl(string $method, EditorOwner $owner): string
{
    return app(EditorOwnerResource::class)->getAsyncMethodUrl(
        $method,
        params: ['resourceItem' => $owner->getKey()],
        page: blocksPageInstance(),
    );
}

function blockVersion(CmsBlock $block): string
{
    return BlockVersion::of($block);
}

/**
 * A page of the tree, placed by the tree so that its address is derived
 * the way the panel derives it.
 *
 * @param  array<string, mixed>  $attributes
 */
function cmsPage(array $attributes = [], ?CmsPage $parent = null): CmsPage
{
    $page = new CmsPage($attributes + [
        'type' => 'page',
        'name' => ['ru' => 'Страница'],
        'slug' => 'page-'.Str::random(6),
        'is_active' => true,
    ]);

    return app(PageTree::class)->create($page, $parent);
}

/**
 * The rows of an index page: the panel loads its table as a component, so
 * the initial document carries the shell and a skeleton only.
 */
function indexRows(\MoonShine\Laravel\Resources\ModelResource $resource): \Illuminate\Testing\TestResponse
{
    $index = $resource->getIndexPage();

    if ($index === null) {
        throw new RuntimeException('The resource registers no index page.');
    }

    $url = $resource->getCore()->getRouter()->getEndpoints()->component(
        name: $index->getListComponentName(),
        additionally: [
            '_fragment-load' => 'crud-list',
            'pageUri' => $index->getUriKey(),
            'resourceUri' => $resource->getUriKey(),
        ],
    );

    return test()->get($url);
}

function pageResource(): PageResource
{
    return app(PageResource::class);
}

/**
 * The url of one editor command of the page resource, exactly as the rendered
 * card posts to it.
 */
function pageEditorUrl(string $method, CmsPage $page): string
{
    $blocksPage = pageResource()->getPages()->first(
        static fn ($candidate): bool => $candidate instanceof PageBlocksPage,
    );

    return pageResource()->getAsyncMethodUrl(
        $method,
        params: ['resourceItem' => $page->getKey()],
        page: $blocksPage,
    );
}

/**
 * Submit a form of the panel the way the panel does it: over ajax, so that a
 * refusal comes back as 422 with the errors by field.
 *
 * @param  array<string, mixed>  $data
 */
function submitForm(string $url, array $data, string $method = 'POST'): \Illuminate\Testing\TestResponse
{
    return test()->call($method, $url, $data, [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);
}

/**
 * The block types the library modal drew. The library answers with the markup
 * of the tiles and not with a payload, so the codes are read back off it.
 *
 * @return list<string>
 */
function libraryEntryCodes(string $html): array
{
    preg_match_all('/data-cms-entry="([^"]+)"/', $html, $matches);

    return array_values($matches[1]);
}

/**
 * @return list<string>
 */
function libraryCategoryCodes(string $html): array
{
    preg_match_all('/data-cms-category="([^"]+)"/', $html, $matches);

    return array_values($matches[1]);
}

/**
 * The addresses the fields hand to the client travel inside json, so their
 * slashes arrive escaped, once or twice over depending on how deep the json
 * sits. A test asks about the address and not about the escaping.
 */
function plainUrls(string $html): string
{
    return str_replace('\\', '', $html);
}

/**
 * How many times the page carries the dialog of the media picker.
 */
function pickerDialogCount(string $html): int
{
    return substr_count($html, 'modal_toggled:'.PickerModal::NAME);
}
