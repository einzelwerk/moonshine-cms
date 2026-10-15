<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\MediaUsages;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Pages\Models\CmsPage;
use Illuminate\Support\Facades\DB;

function libraryPicture(): CmsMedia
{
    return CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'ab/'.uniqid('', true).'/v1/original.jpg',
        'original_name' => 'cover.jpg',
        'mime' => 'image/jpeg',
        'size' => 1024,
    ]);
}

/**
 * @param  array<string, string>  $name
 */
function pageWithName(array $name, string $slug = 'contacts'): CmsPage
{
    return CmsPage::query()->create([
        'type' => 'page',
        'name' => $name,
        'slug' => $slug,
        'path' => '/'.$slug,
        'depth' => 0,
        'is_active' => true,
    ]);
}

function pageUsage(CmsMedia $media, CmsPage $page, string $path = 'cover'): CmsMediaUsage
{
    return CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsPage::MORPH_ALIAS,
        'usable_id' => $page->getKey(),
        'field' => 'content',
        'path' => $path,
    ]);
}

it('names a page by its name in the default language', function (): void {
    $page = pageWithName(['ru' => 'Контакты', 'en' => 'Contacts']);
    $media = libraryPicture();
    pageUsage($media, $page);

    $label = app(MediaUsages::class)->of($media)[0]->owner;

    expect($label->title)->toBe('Контакты')
        ->and($label->type)->toBe('page')
        ->and($label->ownerType)->toBe('cms_page')
        ->and($label->ownerId)->toEqual($page->getKey())
        ->and($label->url)->toBeNull();
});

it('falls back to the address of a page nobody named', function (): void {
    $page = pageWithName([], 'about');
    $media = libraryPicture();
    pageUsage($media, $page);

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)->toBe('/about');
});

it('names a page that no usage points at directly', function (): void {
    $page = pageWithName(['ru' => 'Главная'], 'home');

    expect(app(UsageLabelRegistry::class)->for(CmsPage::MORPH_ALIAS, $page->getKey())->title)
        ->toBe('Главная');
});

it('falls back when the page behind the row is gone', function (): void {
    $media = libraryPicture();

    CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsPage::MORPH_ALIAS,
        'usable_id' => 9999,
        'field' => 'content',
        'path' => 'cover',
    ]);

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)->toBe('cms_page #9999');
});

function blockOfPage(CmsPage $page, string $name): CmsBlock
{
    return CmsBlock::create([
        'blockable_type' => CmsPage::MORPH_ALIAS,
        'blockable_id' => $page->getKey(),
        'type' => 'text',
        'name' => $name,
    ]);
}

function blockUsage(CmsMedia $media, CmsBlock $block, string $path): CmsMediaUsage
{
    return CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsBlock::MORPH_ALIAS,
        'usable_id' => $block->getKey(),
        'field' => CmsBlock::MEDIA_USAGE_FIELD,
        'path' => $path,
    ]);
}

it('puts two blocks of one page under one group with two sources', function (): void {
    $page = pageWithName(['ru' => 'Главная'], 'home');
    $first = blockOfPage($page, 'Первый');
    $second = blockOfPage($page, 'Второй');

    $media = libraryPicture();
    blockUsage($media, $first, 'cover');
    blockUsage($media, $second, 'items.0.image');
    blockUsage($media, $second, 'items.1.image');

    $groups = app(MediaUsages::class)->of($media);

    // One page to open, two of its blocks to scroll to.
    expect($groups)->toHaveCount(1)
        ->and($groups[0]->owner->title)->toBe('Главная')
        ->and($groups[0]->owner->type)->toBe('page')
        ->and($groups[0]->owner->ownerType)->toBe('cms_page')
        ->and($groups[0]->owner->ownerId)->toEqual($page->getKey())
        ->and($groups[0]->sources)->toHaveCount(2)
        ->and($groups[0]->sources[0]->type)->toBe('cms_block')
        ->and($groups[0]->sources[0]->id)->toEqual($first->getKey())
        ->and($groups[0]->sources[0]->fields)->toBe(['cover'])
        ->and($groups[0]->sources[1]->id)->toEqual($second->getKey())
        ->and($groups[0]->sources[1]->fields)->toBe(['items.0.image', 'items.1.image'])
        ->and($groups[0]->fields())->toBe(['cover', 'items.0.image', 'items.1.image']);
});

it('keeps two pages apart and orders them by their name', function (): void {
    $home = pageWithName(['ru' => 'Главная'], 'home');
    $about = pageWithName(['ru' => 'Афиша'], 'afisha');

    $media = libraryPicture();
    blockUsage($media, blockOfPage($home, 'Первый'), 'cover');
    blockUsage($media, blockOfPage($about, 'Первый'), 'cover');

    $groups = app(MediaUsages::class)->of($media);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->owner->title)->toBe('Афиша')
        ->and($groups[1]->owner->title)->toBe('Главная');
});

it('reads a listing of twenty rows across two pages in a handful of queries', function (): void {
    $media = libraryPicture();

    foreach (['home', 'afisha'] as $slug) {
        $page = pageWithName(['ru' => 'Страница '.$slug], $slug);

        foreach (['Первый', 'Второй'] as $name) {
            $block = blockOfPage($page, $name);

            for ($i = 0; $i < 5; $i++) {
                blockUsage($media, $block, 'items.'.$i.'.image');
            }
        }
    }

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $groups = app(MediaUsages::class)->of($media);

    $queries = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    // The rows, the blocks behind them, the pages above those: three, and the
    // same three whether the listing holds twenty rows or two thousand.
    // Without the memo and the eager load every row would cost two.
    expect($groups)->toHaveCount(2)
        ->and($queries)->toBeLessThanOrEqual(6);
});
