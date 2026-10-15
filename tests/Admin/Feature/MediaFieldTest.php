<?php

declare(strict_types=1);

/*
 * The picture field of `ewk/moonshine-media` as the CMS draws it: inside the
 * block editor and on the metadata tab of a page. The field itself is tested
 * by the media library; these cases are about what the CMS adds around it.
 */

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('draws the dialog for an editor whose only picture lives in a slider', function (): void {
    $owner = editorOwner();

    // A repeater row is rendered once as a hidden template and cloned per row,
    // so a field inside one never draws the dialog: the editor draws it
    // instead, see \Ewk\Cms\Media\MoonShine\Library\PickerModal.
    cmsBlock($owner, ['type' => 'slides', 'name' => 'Слайдер', 'content' => [
        'slides' => [['title' => ['ru' => 'Первый']], ['title' => ['ru' => 'Второй']]],
    ]]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect(pickerDialogCount($html))->toBe(1)
        // The field and the editor both ask for the assets of the media
        // library; the page carries them once.
        ->and(substr_count($html, "Alpine.data('cmsMediaField'"))->toBe(1);
});

it('offers no description where the writer of the form stores none', function (): void {
    $page = cmsPage(['slug' => 'agb']);

    $html = $this->get(app(\Ewk\MoonShineCms\MoonShine\Resources\PageResource::class)->getFormPageUrl($page->getKey()))
        ->assertOk()
        ->getContent();

    // The row of the metadata has a column for the picture and none for its
    // caption, see SaveSeoMetadata: an input dropped on the way to the
    // database is worse than no input at all.
    expect($html)->toContain('name="seo[og_image][ru][id]"')
        ->and($html)->not->toContain('name="seo[og_image][metadata][alt][ru]"')
        ->and($html)->not->toContain('name="seo[tw_image][metadata][alt][ru]"');
});

it('carries the code of the picture fields on an editor that has no picture yet', function (): void {
    $owner = editorOwner();

    // A text block only: no field of the page constructs a picker, so the
    // assets of the media library come from the editor itself. A block with
    // a picture inserted later redraws its fragment, never the head.
    cmsBlock($owner, ['type' => 'text', 'name' => 'Текст']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->not->toContain('x-data="cmsMediaField')
        ->and($html)->toContain("Alpine.data('cmsMediaField'")
        ->and($html)->toContain("Alpine.data('cmsMediaAttachments'")
        ->and($html)->toContain('.cms-media {')
        ->and(substr_count($html, "Alpine.data('cmsMediaField'"))->toBe(1);
});
