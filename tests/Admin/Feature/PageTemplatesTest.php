<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Models\CmsPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

/**
 * The address the tile of a template posts to: the command lives on the
 * resource of the pages, because the templates are shown inside their section
 * and not on a page of their own.
 */
function assembleUrl(string $type): string
{
    return pageResource()->getAsyncMethodUrl(
        'assembleTemplate',
        params: ['type' => $type],
        page: pageResource()->getIndexPage(),
    );
}

/**
 * The tree of the pages as the panel draws it, tiles of the templates
 * included.
 */
function templatesHtml(): string
{
    return (string) test()
        ->get(pageResource()->getIndexPageUrl().'?_fragment-load=cms-page-tree')
        ->assertOk()
        ->getContent();
}

it('shows a tile per template type inside the pages, with its state and a way into the editor', function (): void {
    $assembled = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);

    $html = templatesHtml();

    expect($html)->toContain((string) __('moonshine-cms::pages.templates'))
        ->toContain('data-cms-template="concert"')
        ->toContain('data-cms-template="exhibition"')
        ->toContain('Шаблон концерта')
        ->toContain('Шаблон выставки')
        // A free type and a singleton have no template and no tile.
        ->not->toContain('data-cms-template="page"')
        ->and($html)->toContain((string) __('moonshine-cms::pages.template_ready'))
        ->and($html)->toContain((string) __('moonshine-cms::pages.template_missing'))
        ->and($html)->toContain((string) __('moonshine-cms::pages.assemble_template'))
        ->and($html)->toContain(pageResource()->blockEditorPageUrl($assembled->getKey()));
});

it('describes a template by the shape of the addresses it answers', function (): void {
    $html = templatesHtml();

    // The placeholders of the declaration are filled in: an editor reads the
    // address, not the grammar of the pattern.
    expect($html)->toContain('/concerts/'.__('moonshine-cms::pages.template_path_placeholder'))
        ->and($html)->not->toContain('/concerts/{concert}');
});

it('asks before it assembles and answers a post only', function (): void {
    $html = templatesHtml();

    expect($html)->toContain('data-cms-assemble');

    // The method writes a row, so the address is closed to a GET: an <img src>
    // on any page the editor opens would otherwise assemble a template.
    $this->get(assembleUrl('concert'))->assertStatus(405);

    expect(CmsPage::query()->count())->toBe(0);
});

it('assembles a template once from the starter set and then leads into it', function (): void {
    // The button posts and reads the json envelope back: a message to toast
    // and the redirect the browser follows.
    $this->post(assembleUrl('concert'))
        ->assertOk()
        ->assertJsonPath('message', __('moonshine-cms::pages.template_assembled'));

    $page = CmsPage::query()->where('type', 'concert')->sole();

    // Inactive until an editor switches it on; an unregistered starter code
    // is skipped, not fatal.
    expect($page->is_active)->toBeFalse()
        ->and($page->blocks()->pluck('type')->all())->toBe(['schedule', 'text'])
        ->and($page->path)->toBe('/concerts/{concert}');

    // A second press assembles nothing and leads into the template that is
    // already there.
    $this->post(assembleUrl('concert'))
        ->assertOk()
        ->assertJsonPath('redirect', pageResource()->blockEditorPageUrl($page->getKey()));

    expect(CmsPage::query()->where('type', 'concert')->count())->toBe(1);
});

it('refuses to assemble a type that is not a template of the registry', function (): void {
    $this->post(assembleUrl('page'))->assertStatus(422);
    $this->post(assembleUrl('nothing'))->assertStatus(422);

    expect(CmsPage::query()->count())->toBe(0);
});
