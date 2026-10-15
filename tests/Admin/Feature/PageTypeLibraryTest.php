<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Models\CmsPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

/**
 * @return list<string>
 */
function pageLibraryCodes(CmsPage $page): array
{
    $response = test()->get(pageEditorUrl('cmsBlockLibrary', $page));
    $response->assertOk();

    return libraryEntryCodes($response->getContent() ?: '');
}

it('offers a template block only on the page type its placement declares', function (): void {
    $ordinary = cmsPage(['slug' => 'agb']);
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);

    $onOrdinary = pageLibraryCodes($ordinary);
    $onTemplate = pageLibraryCodes($template);

    expect($onOrdinary)->toContain('text')
        ->and($onOrdinary)->not->toContain('schedule')
        ->and($onTemplate)->toContain('schedule')
        ->and($onTemplate)->toContain('text');
});

it('refuses on the server to insert a block the page type does not allow', function (): void {
    $page = cmsPage(['slug' => 'agb']);

    $this->post(pageEditorUrl('insertCmsBlock', $page), ['code' => 'schedule'])->assertStatus(422);

    expect($page->blocks()->count())->toBe(0);

    $this->post(pageEditorUrl('insertCmsBlock', $page), ['code' => 'text'])->assertOk();

    expect($page->blocks()->sole()->type)->toBe('text');
});
