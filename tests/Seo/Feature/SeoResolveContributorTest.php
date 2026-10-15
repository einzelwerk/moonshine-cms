<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry;
use Ewk\Cms\Pages\Http\Resolve\ResolveDocument;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Resolving\ResolvedPath;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Seo\JsonLd\JsonLdRegistry;
use Ewk\Cms\Seo\Resolve\SeoResolveContributor;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\StaticJsonLdBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

function seoPage(array $attributes = []): CmsPage
{
    $page = new CmsPage($attributes + [
        'type' => 'page',
        'name' => ['ru' => 'Афиша', 'en' => 'Afisha'],
        'slug' => 'afisha',
        'is_active' => true,
    ]);
    $page->path = '/afisha';
    $page->depth = 0;
    $page->save();

    return $page;
}

function seoDocument(ResolvedPath $resolved, ?CmsVersion $version = null, string $locale = 'ru'): ResolveDocument
{
    return new ResolveDocument(
        $resolved,
        $version ?? new CmsVersion(['published_at' => new Carbon('2026-09-14 10:00:00')]),
        new ResolvedParameters(),
        $locale,
    );
}

function contributeTo(ResolveDocument $document): ResolveDocument
{
    app(SeoResolveContributor::class)->contribute($document, Request::create('/api/v1/resolve'));

    return $document;
}

it('registers itself in the registry of the resolver', function (): void {
    $contributors = app(ResolveContributorRegistry::class)->all();

    expect($contributors)->toHaveCount(1)
        ->and($contributors[0])->toBeInstanceOf(SeoResolveContributor::class);
});

it('writes the metadata object and the structured data into the document', function (): void {
    $page = seoPage();
    Make::metadata($page, ['title' => ['ru' => 'Афиша Акме']]);

    $document = contributeTo(seoDocument(ResolvedPath::page('/afisha', $page, PageType::make('page'))));

    expect($document->get('metadata'))->toHaveKeys(['title', 'description', 'alternates', 'openGraph', 'twitter', 'robots'])
        ->and($document->get('metadata')['title'])->toBe('Афиша Акме')
        ->and($document->get('metadata')['alternates']['canonical'])->toBe('https://example.com/ru/afisha')
        ->and($document->get('json_ld'))->toBe([]);
});

it('falls the title back to the name of the page in the language of the request', function (): void {
    $page = seoPage();

    $ru = contributeTo(seoDocument(ResolvedPath::page('/afisha', $page, PageType::make('page')), locale: 'ru'));
    $en = contributeTo(seoDocument(ResolvedPath::page('/afisha', $page, PageType::make('page')), locale: 'en'));

    expect($ru->get('metadata')['title'])->toBe('Афиша')
        ->and($en->get('metadata')['title'])->toBe('Afisha');
});

it('takes the fallback of a template address from the subject and not from the template page', function (): void {
    $template = seoPage(['type' => 'tour', 'slug' => 'tours']);
    $subject = Make::owner(['ru' => 'Щелкунчик', 'en' => 'Nutcracker']);

    $document = contributeTo(seoDocument(
        ResolvedPath::subject('/tours/nutcracker', $template, PageType::make('tour'), $subject),
    ));

    // The name of the template page ("Афиша") never reaches a subject page:
    // the owner answers for itself through ProvidesSeoFallbacks.
    expect($document->get('metadata')['title'])->toBe('Щелкунчик')
        ->and($document->get('metadata')['alternates']['canonical'])->toBe('https://example.com/ru/tours/nutcracker');
});

it('leaves an unpublished page without language versions', function (): void {
    $page = seoPage();

    $document = contributeTo(seoDocument(
        ResolvedPath::page('/afisha', $page, PageType::make('page')),
        new CmsVersion(['published_at' => null]),
    ));

    expect($document->get('metadata')['alternates']['languages'])->toBe([]);
});

it('hands the context to every registered json-ld builder', function (): void {
    app(JsonLdRegistry::class)->register(new StaticJsonLdBuilder([['@type' => 'Event']]));

    $page = seoPage();
    $document = contributeTo(seoDocument(ResolvedPath::page('/afisha', $page, PageType::make('page'))));

    expect($document->get('json_ld'))->toBe([['@type' => 'Event']]);
});
