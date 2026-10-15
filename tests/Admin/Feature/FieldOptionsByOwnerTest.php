<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageBlocksPage;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

/**
 * The block editor of one page, and the commands it posts to.
 */
function ownerEditorUrl(string $method, CmsPage $page): string
{
    $blocksPage = pageResource()->getPages()->first(static fn ($candidate): bool => $candidate instanceof PageBlocksPage);

    return pageResource()->getAsyncMethodUrl(
        $method,
        params: ['resourceItem' => $page->getKey()],
        page: $blocksPage,
    );
}

function ownerEditorHtml(CmsPage $page): string
{
    return test()->get(pageResource()->blockEditorPageUrl($page->getKey()))->assertOk()->getContent();
}

/**
 * @param  array<string, mixed>  $content
 */
function sourcedBlockOn(CmsPage $page, array $content = []): CmsBlock
{
    return cmsBlock($page, ['type' => 'sourced', 'name' => 'Подборка', 'content' => $content]);
}

it('offers the source of the page entity on a template and drops it elsewhere', function (): void {
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);
    $ordinary = cmsPage(['slug' => 'agb']);

    sourcedBlockOn($template);
    sourcedBlockOn($ordinary);

    $onTemplate = ownerEditorHtml($template);
    $onOrdinary = ownerEditorHtml($ordinary);

    // Both keep a choice, so both draw the selector.
    expect($onTemplate)->toContain('name="content[source]"')
        ->and($onTemplate)->toContain('value="subject"')
        ->and($onOrdinary)->toContain('name="content[source]"')
        ->and($onOrdinary)->not->toContain('value="subject"');
});

it('shows no selector where the owner leaves one value and carries it hidden instead', function (): void {
    $ordinary = cmsPage(['slug' => 'agb']);

    // The block was written on a page that had a subject, and the value it
    // kept is not on offer here: the card carries the only one that is.
    sourcedBlockOn($ordinary, ['origin' => 'subject']);

    $html = ownerEditorHtml($ordinary);

    preg_match('#<input[^>]*name="content\[origin\]"[^>]*>#', $html, $input);
    preg_match('#<select[^>]*name="content\[origin\]"[^>]*>#', $html, $select);

    expect($input[0] ?? '')->toContain('type="hidden"')
        // The value the owner leaves, and not the one the row still holds.
        ->and($input[0] ?? '')->toContain('value="manual"')
        ->and($select)->toBe([]);
});

it('settles the value of a field without a choice whatever the request carried', function (): void {
    $ordinary = cmsPage(['slug' => 'agb']);
    $block = sourcedBlockOn($ordinary);

    $response = $this->post(ownerEditorUrl('saveCmsBlock', $ordinary), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => ['origin' => 'subject', 'source' => 'manual'],
    ]);

    $response->assertOk();

    expect($block->refresh()->content['origin'])->toBe('manual');
});

it('refuses a source the type of the page does not offer', function (): void {
    $ordinary = cmsPage(['slug' => 'agb']);
    $block = sourcedBlockOn($ordinary);

    $response = $this->postJson(ownerEditorUrl('saveCmsBlock', $ordinary), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => ['source' => 'subject'],
    ]);

    // The form is a hint and the rule is the guarantee: a crafted request
    // must not store a source that cannot work on this page.
    $response->assertStatus(422);

    expect($block->refresh()->content)->toBe([]);
});

it('accepts the same source on a page whose type has a subject', function (): void {
    $template = cmsPage(['type' => 'concert', 'slug' => 'concert-template']);
    $block = sourcedBlockOn($template);

    $response = $this->post(ownerEditorUrl('saveCmsBlock', $template), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Подборка',
        'is_active' => true,
        'content' => ['source' => 'subject'],
    ]);

    $response->assertOk();

    expect($block->refresh()->content['source'])->toBe('subject')
        // The field without a choice is settled by the owner as well, and
        // here the owner has a subject.
        ->and($block->content['origin'])->toBe('subject');
});
