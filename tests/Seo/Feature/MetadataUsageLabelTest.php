<?php

declare(strict_types=1);

use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\MediaUsages;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Tests\Fixtures\Make;

it('names the record the card describes and not the metadata row', function (): void {
    $page = Make::page();
    $media = Make::media();
    Make::metadata($page, ['og_image' => ['ru' => $media->id], 'tw_image' => ['ru' => $media->id]]);

    $groups = app(MediaUsages::class)->of($media);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->owner->title)->toBe('Контакты')
        ->and($groups[0]->owner->type)->toBe('metadata')
        ->and($groups[0]->owner->ownerType)->toBe('cms_page')
        ->and($groups[0]->owner->ownerId)->toEqual($page->getKey())
        ->and($groups[0]->owner->url)->toBeNull()
        ->and($groups[0]->fields())->toBe(['og_image.ru', 'tw_image.ru'])
        ->and($groups[0]->sources)->toHaveCount(1)
        ->and($groups[0]->sources[0]->type)->toBe('cms_metadata');
});

it('names an owner nobody registered as well as it can', function (): void {
    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $label = app(MediaUsages::class)->of($media)[0]->owner;

    expect($label->type)->toBe('metadata')
        ->and($label->title)->toBe($owner->getMorphClass().' #'.$owner->getKey())
        ->and($label->ownerType)->toBe($owner->getMorphClass());
});

it('falls back when the metadata row behind the usage is gone', function (): void {
    $media = Make::media();

    CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsMetadata::MORPH_ALIAS,
        'usable_id' => 4242,
        'field' => 'og_image',
        'path' => 'og_image.ru',
    ]);

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)->toBe('cms_metadata #4242');
});
