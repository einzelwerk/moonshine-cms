<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Layouts\Selection\LayoutResolveContributor;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Http\Resolve\ResolveDocument;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Resolving\ResolvedPath;
use Ewk\Cms\Pages\Types\PageType;
use Illuminate\Http\Request;

/**
 * The layout code of `GET /v1/resolve` is the selected layout and not the raw
 * column. The document is built by hand here: the resolver of the pages package
 * writes the body, this package only replaces one value in it.
 */
function layoutResolveDocument(?string $layoutCode): ResolveDocument
{
    $page = new CmsPage();
    $page->uuid = '00000000-0000-4000-8000-000000000001';
    $page->path = '/about';
    $page->layout_code = $layoutCode;

    $document = new ResolveDocument(
        ResolvedPath::page('/about', $page, PageType::make('page')),
        null,
        new ResolvedParameters(),
        'ru',
    );

    $document->set('page', ['uuid' => $page->uuid, 'layout_code' => $layoutCode]);

    return $document;
}

function contributeLayout(ResolveDocument $document): mixed
{
    app(LayoutResolveContributor::class)->contribute($document, Request::create('/api/v1/resolve'));

    $page = $document->get('page');

    return is_array($page) ? ($page['layout_code'] ?? null) : null;
}

it('hands out the layout the page names', function (): void {
    Make::layout('default', default: true);
    Make::layout('text', sortOrder: 1);

    expect(contributeLayout(layoutResolveDocument('text')))->toBe('text');
});

it('replaces a code nobody can draw with the default layout', function (): void {
    Make::layout('default', default: true);
    Make::layout('retired', active: false, sortOrder: 1);

    expect(contributeLayout(layoutResolveDocument('retired')))->toBe('default')
        ->and(contributeLayout(layoutResolveDocument(null)))->toBe('default')
        ->and(contributeLayout(layoutResolveDocument('never-shipped')))->toBe('default');
});

it('answers null instead of failing when the installation has no default layout', function (): void {
    Make::layout('text');

    // A public address must not answer 500 because an editor left the flag
    // unset, and the code of a layout that cannot be drawn would be a lie.
    expect(contributeLayout(layoutResolveDocument('never-shipped')))->toBeNull()
        ->and(contributeLayout(layoutResolveDocument(null)))->toBeNull();
});

it('leaves an answer without a page untouched', function (): void {
    Make::layout('default', default: true);

    $document = layoutResolveDocument('text');
    $document->set('page', null);

    expect(contributeLayout($document))->toBeNull();
});
