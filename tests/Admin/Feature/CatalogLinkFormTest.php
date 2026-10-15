<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Pages\Models\CmsPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

/**
 * The catalog the link points at: a page with one listing block on it, and that
 * block carries the anchor the filters are namespaced by.
 */
function catalogPage(): CmsPage
{
    $catalog = cmsPage(['slug' => 'afisha']);

    cmsBlock($catalog, ['type' => 'feed', 'name' => 'Афиша', 'anchor' => 'afisha']);

    return $catalog;
}

/**
 * @return array<string, mixed>
 */
function renderedLink(CmsPage $page): array
{
    $rendered = app(BlockRenderer::class)->render($page, request());
    $link = $rendered[0]['data']['show_all'] ?? [];

    return is_array($link) ? $link : [];
}

it('offers a page, the filters and the anchor for a catalog link', function (): void {
    $catalog = catalogPage();
    $page = cmsPage(['slug' => 'home-ish']);

    cmsBlock($page, [
        'type' => 'sourced',
        'name' => 'Подборка',
        'content' => [
            'show_all' => [
                'kind' => 'list',
                'attributes' => ['page' => $catalog->uuid, 'filters' => ['city' => 'berlin'], 'anchor' => 'afisha'],
                'new_tab' => false,
            ],
        ],
    ]);

    $html = test()->get(pageResource()->blockEditorPageUrl($page->getKey()))->assertOk()->getContent();

    expect($html)->toContain("base: 'content[show_all]'")
        // The chosen page comes back with its caption and never as a uuid to
        // read, and the stored filter comes back as a value of the form.
        ->and($html)->toContain($catalog->uuid)
        ->and($html)->toContain('Страница (/afisha)')
        ->and($html)->toContain('\u0022city\u0022:\u0022berlin\u0022')
        ->and($html)->toContain('listAnchor: \'afisha\'')
        // The values of the filters come from the catalogue itself, so the
        // form asks for them instead of offering a key and a value to type.
        ->and(plainUrls($html))->toContain('cms/link/catalog')
        ->and($html)->not->toContain('name="content[show_all][attributes][list]"');
});

it('saves a catalog link through the editor and resolves it to an address with the filters', function (): void {
    $catalog = catalogPage();
    $page = cmsPage(['slug' => 'home-ish']);
    $block = cmsBlock($page, ['type' => 'sourced', 'name' => 'Подборка']);

    $response = $this->post(pageEditorUrl('saveCmsBlock', $page), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => [
            'show_all' => [
                'kind' => 'list',
                'attributes' => ['page' => $catalog->uuid, 'filters' => ['city' => 'berlin']],
            ],
        ],
    ]);

    $response->assertOk();

    $stored = $block->refresh()->content['show_all'] ?? [];

    expect($stored['kind'] ?? null)->toBe('list')
        ->and($stored['attributes']['page'] ?? null)->toBe($catalog->uuid)
        ->and($stored['attributes']['filters'] ?? null)->toBe(['city' => 'berlin'])
        // Nothing resolved is stored, and the address is built on render.
        ->and(renderedLink($page)['href'] ?? null)->toBe($catalog->path.'?b[afisha][city]=berlin');
});

it('keeps a catalog link without filters at the plain address of its page', function (): void {
    $catalog = catalogPage();
    $page = cmsPage(['slug' => 'home-ish']);
    $block = cmsBlock($page, ['type' => 'sourced', 'name' => 'Подборка']);

    $this->post(pageEditorUrl('saveCmsBlock', $page), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => [
            'show_all' => ['kind' => 'list', 'attributes' => ['page' => $catalog->uuid]],
        ],
    ])->assertOk();

    expect(renderedLink($page)['href'] ?? null)->toBe($catalog->path);
});

it('delivers a catalog link whose anchor names no block of the page as broken', function (): void {
    $catalog = catalogPage();
    $page = cmsPage(['slug' => 'home-ish']);
    $block = cmsBlock($page, ['type' => 'sourced', 'name' => 'Подборка']);

    $this->post(pageEditorUrl('saveCmsBlock', $page), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => [
            'show_all' => [
                'kind' => 'list',
                'attributes' => [
                    'page' => $catalog->uuid,
                    'filters' => ['city' => 'berlin'],
                    'anchor' => 'no-such-list',
                ],
            ],
        ],
    ])->assertOk();

    expect(renderedLink($page))->toHaveKey('href')
        ->and(renderedLink($page)['href'])->toBeNull();
});
