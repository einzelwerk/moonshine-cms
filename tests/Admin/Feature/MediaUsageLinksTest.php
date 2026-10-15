<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Media\Enums\FileKind;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\MoonShine\Library\UsageLinkRegistry;
use Ewk\Cms\Media\MoonShine\Library\UsageLinks;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\MoonShine\Resources\FooterResource;
use Ewk\MoonShineCms\MoonShine\Resources\HeaderResource;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\ConfiguredPageResource;
use Illuminate\Support\Facades\Storage;

/*
 * What the CMS registers with «where this file is used» of the media library:
 * the resource that edits each of its records and the caption of each kind of
 * usage. The library itself is tested in `ewk/moonshine-media`; these cases
 * are about the entries of this package, registered by a provider that runs
 * before the one binding the registry.
 */

beforeEach(function (): void {
    $this->actingAsAdmin();
    Storage::fake('public');

    config()->set('moonshine.locale', 'ru');
    app()->setLocale('ru');
});

it('registers the resource that edits every record of the CMS', function (): void {
    $links = app(UsageLinkRegistry::class);

    expect($links->resourceFor(CmsPage::MORPH_ALIAS))->toBe(PageResource::class)
        ->and($links->resourceFor(CmsSharedBlock::MORPH_ALIAS))->toBe(SharedBlockResource::class)
        ->and($links->resourceFor(CmsHeader::MORPH_ALIAS))->toBe(HeaderResource::class)
        ->and($links->resourceFor(CmsFooter::MORPH_ALIAS))->toBe(FooterResource::class);
});

it('reads the resource of pages from the configuration when a link is built', function (): void {
    $links = app(UsageLinkRegistry::class);

    config()->set('moonshine-cms.page_resource', ConfiguredPageResource::class);

    expect($links->resourceFor(CmsPage::MORPH_ALIAS))->toBe(ConfiguredPageResource::class);
});

it('names the five kinds of usage of the CMS in the language of the panel', function (): void {
    $links = app(UsageLinkRegistry::class);

    expect($links->captionFor('page'))->toBe('Страница')
        ->and($links->captionFor('shared_block'))->toBe('Общий блок')
        ->and($links->captionFor('header'))->toBe('Шапка')
        ->and($links->captionFor('footer'))->toBe('Подвал')
        ->and($links->captionFor('metadata'))->toBe('Описание для поиска');

    app()->setLocale('en');

    expect($links->captionFor('page'))->toBe('Page')
        ->and($links->captionFor('metadata'))->toBe('Search description');
});

it('tells the library where a page that uses a file is edited', function (): void {
    $media = CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'ab/'.uniqid('', false).'/v1/original.jpg',
        'original_name' => 'poster.jpg',
        'name' => 'Афиша',
        'mime' => 'image/jpeg',
        'size' => 2_516_582,
        'kind' => FileKind::IMAGE,
    ]);
    $page = cmsPage(['slug' => 'festival', 'name' => ['ru' => 'Фестиваль']]);

    CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsPage::MORPH_ALIAS,
        'usable_id' => $page->getKey(),
        'field' => 'image',
        'path' => 'content.image',
    ]);

    $details = $this->get(route('moonshine.cms.media.show', ['uuid' => $media->uuid]))->assertOk()->json('media');

    expect($details['usages'])->toHaveCount(1)
        ->and($details['usages'][0]['type'])->toBe('Страница')
        ->and($details['usages'][0]['title'])->toContain('Фестиваль')
        ->and($details['usages'][0]['url'])->toContain('page-resource');
});

it('builds the address of a page of the CMS when its resolver brought none', function (): void {
    $page = cmsPage(['slug' => 'about', 'name' => ['ru' => 'О нас']]);

    $label = new UsageOwnerLabel(
        title: 'О нас',
        url: '',
        type: 'page',
        ownerType: CmsPage::MORPH_ALIAS,
        ownerId: $page->getKey(),
    );

    expect(app(UsageLinks::class)->url($label))->toContain('page-resource');
});
