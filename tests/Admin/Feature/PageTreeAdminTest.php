<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Models\CmsPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function moveUrl(): string
{
    return pageResource()->getAsyncMethodUrl('movePage', page: pageResource()->getIndexPage());
}

/**
 * The tree as the panel draws it: the fragment of the index page.
 */
function treeHtml(): string
{
    return (string) test()->get(pageResource()->getIndexPageUrl().'?_fragment-load=cms-page-tree')->assertOk()->getContent();
}

it('draws the pages nested under their parents, singletons marked, templates out of the tree', function (): void {
    $home = cmsPage(['type' => 'home', 'slug' => 'home', 'name' => ['ru' => 'Главная']]);
    $info = cmsPage(['slug' => 'info', 'name' => ['ru' => 'Информация']]);
    $help = cmsPage(['slug' => 'help', 'name' => ['ru' => 'Помощь']], $info);
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template', 'name' => ['ru' => 'Шаблон концерта']]);

    $html = treeHtml();

    expect($html)->toContain('data-cms-page="'.$info->getKey().'"')
        ->and($html)->toContain('data-cms-path="/info/help"')
        ->and($html)->toContain('Помощь')
        // A template is a tile under the tree and never a node of it: it is
        // neither moved nor deleted.
        ->and($html)->not->toContain('data-cms-page="'.$template->getKey().'"')
        ->and($html)->toContain('data-cms-template="concert"')
        // The child sits inside the list item of its parent.
        ->and(strpos($html, 'data-cms-page="'.$help->getKey().'"'))->toBeGreaterThan(strpos($html, 'data-cms-page="'.$info->getKey().'"'))
        ->and($html)->toContain(__('moonshine-cms::pages.fixed_badge'))
        ->and($html)->toContain('data-cms-add-child="'.$info->getKey().'"')
        ->and($html)->toContain(pageResource()->blockEditorPageUrl($home->getKey()));

    // The singleton has no drag handle; the free page has one.
    preg_match('#data-cms-page="'.$home->getKey().'".*?</div>#s', $html, $singleton);
    preg_match('#data-cms-page="'.$info->getKey().'".*?</div>#s', $html, $free);

    expect($singleton[0] ?? '')->not->toContain('draggable="true"')
        ->and($free[0] ?? '')->toContain('draggable="true"');
});

it('moves a node under another parent and rewrites the addresses of its subtree', function (): void {
    $info = cmsPage(['slug' => 'info']);
    $help = cmsPage(['slug' => 'help'], $info);
    $faq = cmsPage(['slug' => 'faq'], $help);
    $about = cmsPage(['slug' => 'about']);

    $this->postJson(moveUrl(), ['page' => $help->getKey(), 'parent' => $about->getKey(), 'position' => null])
        ->assertOk()
        ->assertJsonPath('path', '/about/help');

    expect($help->refresh()->parent_id)->toBe($about->getKey())
        ->and($help->depth)->toBe(1)
        ->and($faq->refresh()->path)->toBe('/about/help/faq')
        ->and($info->refresh()->path)->toBe('/info');
});

it('reorders siblings by position and lifts a node to the root', function (): void {
    $first = cmsPage(['slug' => 'first']);
    $second = cmsPage(['slug' => 'second']);
    $child = cmsPage(['slug' => 'child'], $first);

    $this->postJson(moveUrl(), ['page' => $second->getKey(), 'parent' => null, 'position' => 0])->assertOk();

    expect($second->refresh()->sort_order)->toBeLessThan($first->refresh()->sort_order);

    $this->postJson(moveUrl(), ['page' => $child->getKey(), 'parent' => null, 'position' => null])
        ->assertOk()
        ->assertJsonPath('path', '/child');

    expect($child->refresh()->parent_id)->toBeNull()
        ->and($child->depth)->toBe(0);
});

it('refuses a move the tree refuses and reports why', function (): void {
    $home = cmsPage(['type' => 'home', 'slug' => 'home']);
    $info = cmsPage(['slug' => 'info']);
    $help = cmsPage(['slug' => 'help'], $info);
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);

    // Into its own subtree.
    $this->postJson(moveUrl(), ['page' => $info->getKey(), 'parent' => $help->getKey()])->assertStatus(422);
    // A singleton stays where its declaration puts it.
    $this->postJson(moveUrl(), ['page' => $home->getKey(), 'parent' => $info->getKey()])->assertStatus(422);
    // Under a template.
    $this->postJson(moveUrl(), ['page' => $help->getKey(), 'parent' => $template->getKey()])->assertStatus(422);
    // Onto a path another page holds.
    cmsPage(['slug' => 'help']);
    $this->postJson(moveUrl(), ['page' => $help->getKey(), 'parent' => null])->assertStatus(422);
    // Nothing to move.
    // Moving rewrites paths, so the address does nothing on a GET.
    $this->get(moveUrl())->assertStatus(405);

    $this->postJson(moveUrl(), ['page' => 999, 'parent' => null])->assertStatus(404);
    $this->postJson(moveUrl(), ['page' => $help->getKey(), 'parent' => 999])->assertStatus(404);

    expect($info->refresh()->path)->toBe('/info')
        ->and($help->refresh()->path)->toBe('/info/help')
        ->and($home->refresh()->path)->toBe('/');
});

it('creates a page under the parent chosen on the form, prefilled from the tree', function (): void {
    $info = cmsPage(['slug' => 'info', 'name' => ['ru' => 'Информация']]);

    $this->get(pageResource()->getFormPageUrl(params: ['parent' => $info->getKey()]))
        ->assertOk()
        ->assertSee('name="parent_id"', false)
        ->assertSee('<option selected', false);

    submitForm(pageResource()->getRoute('crud.store'), [
        'name' => ['ru' => 'Помощь'],
        'slug' => 'help',
        'type' => 'page',
        'parent_id' => (string) $info->getKey(),
        'is_active' => '1',
    ])->assertSuccessful();

    $help = CmsPage::query()->where('slug', 'help')->sole();

    expect($help->parent_id)->toBe($info->getKey())
        ->and($help->path)->toBe('/info/help')
        ->and($help->depth)->toBe(1);
});

it('moves an existing page with its subtree when the parent changes on the form', function (): void {
    $info = cmsPage(['slug' => 'info']);
    $help = cmsPage(['slug' => 'help'], $info);
    $faq = cmsPage(['slug' => 'faq'], $help);
    $about = cmsPage(['slug' => 'about']);

    submitForm(pageResource()->getRoute('crud.update', $help->getKey()), [
        'name' => ['ru' => 'Помощь'],
        'slug' => 'support',
        'type' => 'page',
        'parent_id' => (string) $about->getKey(),
        'is_active' => '1',
    ], 'PUT')->assertSuccessful();

    expect($help->refresh()->path)->toBe('/about/support')
        ->and($faq->refresh()->path)->toBe('/about/support/faq');
});

it('offers as parent neither the page itself, nor its subtree, nor a template', function (): void {
    $info = cmsPage(['slug' => 'info', 'name' => ['ru' => 'Информация']]);
    $help = cmsPage(['slug' => 'help', 'name' => ['ru' => 'Помощь']], $info);
    $about = cmsPage(['slug' => 'about', 'name' => ['ru' => 'О нас']]);
    cmsPage(['type' => 'concert', 'slug' => 'concert-template', 'name' => ['ru' => 'Шаблон концерта']]);

    $html = (string) $this->get(pageResource()->getFormPageUrl($info->getKey()))->assertOk()->getContent();
    preg_match('#<select[^>]*name="parent_id".*?</select>#s', $html, $select);

    expect($select[0] ?? '')->toContain('О нас')
        ->and($select[0] ?? '')->not->toContain('Информация')
        ->and($select[0] ?? '')->not->toContain('Помощь')
        ->and($select[0] ?? '')->not->toContain('Шаблон концерта');

    // A parent the select does not offer is refused by the rules too.
    submitForm(pageResource()->getRoute('crud.update', $info->getKey()), [
        'name' => ['ru' => 'Информация'],
        'slug' => 'info',
        'type' => 'page',
        'parent_id' => (string) $help->getKey(),
        'is_active' => '1',
    ], 'PUT')->assertStatus(422)->assertJsonValidationErrors(['parent_id']);

    expect($info->refresh()->path)->toBe('/info');
});

it('ignores a parent on a singleton: its address is declared', function (): void {
    $home = cmsPage(['type' => 'home', 'slug' => 'home']);
    $info = cmsPage(['slug' => 'info']);

    $this->get(pageResource()->getFormPageUrl($home->getKey()))
        ->assertOk()
        ->assertDontSee('name="parent_id"', false);

    submitForm(pageResource()->getRoute('crud.update', $home->getKey()), [
        'name' => ['ru' => 'Главная'],
        'slug' => 'home',
        'type' => 'home',
        'parent_id' => (string) $info->getKey(),
        'is_active' => '1',
    ], 'PUT')->assertSuccessful();

    expect($home->refresh()->parent_id)->toBeNull()
        ->and($home->path)->toBe('/');
});
