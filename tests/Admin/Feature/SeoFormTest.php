<?php

declare(strict_types=1);

use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\MoonShineCms\MoonShine\Fields\SeoSnippet;

/**
 * One file of the library, the way the picker of the tab hands it over: a
 * uuid and nothing else.
 */
function seoMedia(): CmsMedia
{
    return CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'cms/card.jpg',
        'original_name' => 'card.jpg',
        'name' => 'Карточка',
        'mime' => 'image/jpeg',
        'size' => 2048,
    ]);
}

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function seoStoreUrl(): string
{
    return pageResource()->getRoute('crud.store');
}

function seoUpdateUrl(CmsPage $page): string
{
    return pageResource()->getRoute('crud.update', $page->getKey());
}

function seoPayload(array $seo): array
{
    return [
        'name' => ['ru' => 'Условия', 'en' => 'Terms'],
        'slug' => 'agb',
        'type' => 'page',
        'is_active' => '1',
        'seo' => $seo,
    ];
}

it('shows the metadata tab with the counters, the fallback and the address of the site', function (): void {
    $page = cmsPage(['slug' => 'afisha', 'name' => ['ru' => 'Афиша']]);

    $response = test()->get(pageResource()->getFormPageUrl($page->getKey()));

    $response->assertOk()
        ->assertSee('name="seo[title][ru]"', false)
        ->assertSee('name="seo[description][ru]"', false)
        ->assertSee('name="seo[og_title][ru]"', false)
        ->assertSee('name="seo[canonical]"', false)
        // The counter of a recommended length, on the snippet and on the Open
        // Graph texts alike: a number over the recommendation, and the hint
        // that says the value is not cut but may be trimmed in the results.
        ->assertSee('/' . SeoSnippet::TITLE_LENGTH, false)
        ->assertSee('/' . SeoSnippet::DESCRIPTION_LENGTH, false)
        ->assertSee((string) __('cms-seo::seo.counters.recommended', ['max' => SeoSnippet::TITLE_LENGTH]), false)
        ->assertSee((string) __('cms-seo::seo.counters.over'), false)
        // The fallback of an empty title is the caption of the page, shown
        // greyed out in the preview instead of left to be guessed at.
        ->assertSee('Афиша', false)
        ->assertSee('https://example.com', false);

    // Every caption and hint of the tab is translated: a key built from the
    // name of a column (`cms-seo::seo.fields.og_type`) is easy to forget, and
    // the editor then reads the key instead of the label.
    expect($response->getContent())->not->toContain('cms-seo::seo.')
        ->and($response->getContent())->not->toContain('moonshine-cms::seo.');
});

it('stores the metadata of a page it creates', function (): void {
    submitForm(seoStoreUrl(), seoPayload([
        'title' => ['ru' => 'Условия сервиса', 'en' => 'Terms of service'],
        'description' => ['ru' => 'Описание', 'en' => ''],
        'og_title' => ['ru' => 'Условия'],
        'og_type' => 'article',
        'tw_card' => 'summary',
        'robots_index' => '0',
        'robots_follow' => '1',
        'canonical' => '/agb',
    ]))->assertSuccessful();

    $page = CmsPage::query()->where('slug', 'agb')->firstOrFail();
    $metadata = CmsMetadata::of($page);

    expect($metadata)->not->toBeNull()
        ->and($metadata->title)->toBe(['ru' => 'Условия сервиса', 'en' => 'Terms of service'])
        // An empty language is not stored as an empty string: only "not filled
        // in" falls back.
        ->and($metadata->description)->toBe(['ru' => 'Описание'])
        ->and($metadata->og_title)->toBe(['ru' => 'Условия'])
        ->and($metadata->og_type)->toBe('article')
        ->and($metadata->tw_card)->toBe('summary')
        ->and($metadata->robots_index)->toBeFalse()
        ->and($metadata->robots_follow)->toBeTrue()
        ->and($metadata->canonical)->toBe('/agb')
        // The polymorphic alias of a page, not the class of the installation.
        ->and($metadata->model_type)->toBe(CmsPage::MORPH_ALIAS);
});

it('updates the metadata of a page that already has a row', function (): void {
    $page = cmsPage(['slug' => 'agb']);
    CmsMetadata::query()->create([
        'model_type' => $page->getMorphClass(),
        'model_id' => $page->getKey(),
        'title' => ['ru' => 'Старый'],
    ]);

    submitForm(seoUpdateUrl($page), seoPayload([
        'title' => ['ru' => 'Новый', 'en' => 'New'],
    ]), 'PUT')->assertSuccessful();

    expect(CmsMetadata::of($page)->title)->toBe(['ru' => 'Новый', 'en' => 'New'])
        ->and(CmsMetadata::query()->count())->toBe(1);
});

it('writes no row at all for a tab left empty', function (): void {
    submitForm(seoStoreUrl(), seoPayload([
        'title' => ['ru' => '', 'en' => ''],
        'description' => ['ru' => ''],
        'canonical' => '',
        'robots_index' => '1',
        'robots_follow' => '1',
    ]))->assertSuccessful();

    expect(CmsMetadata::query()->count())->toBe(0);
});

it('refuses a canonical address that is neither a path nor a full address', function (string $canonical, bool $valid): void {
    $response = submitForm(seoStoreUrl(), seoPayload(['canonical' => $canonical]));

    $valid
        ? $response->assertSuccessful()
        : $response->assertStatus(422)->assertJsonValidationErrors(['seo.canonical']);
})->with([
    'a path' => ['/afisha', true],
    'a path with a query string' => ['/afisha?b[afisha][page]=2', true],
    'another site' => ['https://example.test/afisha', true],
    'empty' => ['', true],
    'a bare word' => ['afisha', false],
    'protocol relative' => ['//example.test/afisha', false],
    'javascript' => ['javascript:alert(1)', false],
]);

it('keeps a picture of its own for every language', function (): void {
    $page = cmsPage(['slug' => 'agb']);
    $ru = seoMedia();
    $en = seoMedia();

    submitForm(seoUpdateUrl($page), seoPayload([
        'og_image' => ['ru' => ['id' => $ru->uuid], 'en' => ['id' => $en->uuid]],
        'tw_image' => ['ru' => ['id' => ''], 'en' => ['id' => $en->uuid]],
    ]), 'PUT')->assertSuccessful();

    $metadata = CmsMetadata::of($page);

    expect($metadata->og_image)->toBe(['ru' => (int) $ru->getKey(), 'en' => (int) $en->getKey()])
        ->and($metadata->tw_image)->toBe(['en' => (int) $en->getKey()]);

    $html = test()->get(pageResource()->getFormPageUrl($page->getKey()))->assertOk()->getContent();

    // One picker per language, each marked with it, so the one switch of the
    // form shows the picture of the chosen language.
    expect($html)->toContain('name="seo[og_image][ru][id]"')
        ->and($html)->toContain('name="seo[og_image][en][id]"')
        ->and($html)->toContain('value="'.$ru->uuid.'"')
        ->and($html)->toContain('value="'.$en->uuid.'"');
});

it('clears the picture of one language when its field is sent empty and keeps the file', function (): void {
    $page = cmsPage(['slug' => 'agb']);
    $ru = seoMedia();
    $en = seoMedia();

    submitForm(seoUpdateUrl($page), seoPayload([
        'og_image' => ['ru' => ['id' => $ru->uuid], 'en' => ['id' => $en->uuid]],
    ]), 'PUT')->assertSuccessful();

    submitForm(seoUpdateUrl($page), seoPayload([
        'og_image' => ['ru' => ['id' => $ru->uuid], 'en' => ['id' => '']],
    ]), 'PUT')->assertSuccessful();

    // The reference is gone and the file is not: a picture of the library is
    // never deleted by a form that stopped pointing at it.
    expect(CmsMetadata::of($page)->og_image)->toBe(['ru' => (int) $ru->getKey()])
        ->and(CmsMedia::query()->count())->toBe(2);

    submitForm(seoUpdateUrl($page), seoPayload([
        'og_image' => ['ru' => ['id' => ''], 'en' => ['id' => '']],
    ]), 'PUT')->assertSuccessful();

    expect(CmsMetadata::of($page)->og_image)->toBeNull();
});

it('refuses a type and a card value the standards do not declare', function (): void {
    submitForm(seoStoreUrl(), seoPayload(['og_type' => 'nonsense', 'tw_card' => 'nonsense']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['seo.og_type', 'seo.tw_card']);
});
