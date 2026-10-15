<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\MediaUsages;

function chromeUsage(CmsMedia $media, string $usableType, int|string $usableId, string $path = 'logo'): CmsMediaUsage
{
    return CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => $usableType,
        'usable_id' => $usableId,
        'field' => 'content',
        'path' => $path,
    ]);
}

it('names a header and a footer by their own name', function (): void {
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Основная шапка']]);
    $footer = CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Основной подвал']]);

    $media = Make::logo();
    chromeUsage($media, CmsHeader::MORPH_ALIAS, $header->getKey());
    chromeUsage($media, CmsFooter::MORPH_ALIAS, $footer->getKey(), 'payments.0.icon');

    $groups = app(MediaUsages::class)->of($media);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->owner->title)->toBe('Основная шапка')
        ->and($groups[0]->owner->type)->toBe('header')
        ->and($groups[0]->owner->ownerType)->toBe('cms_header')
        ->and($groups[0]->owner->ownerId)->toEqual($header->getKey())
        ->and($groups[1]->owner->title)->toBe('Основной подвал')
        ->and($groups[1]->owner->type)->toBe('footer')
        ->and($groups[1]->fields())->toBe(['payments.0.icon'])
        ->and($groups[1]->sources)->toHaveCount(1)
        ->and($groups[1]->sources[0]->type)->toBe('cms_footer')
        ->and($groups[1]->sources[0]->id)->toEqual($footer->getKey());
});

it('falls back to the alias when the record behind the row is gone', function (): void {
    $media = Make::logo();
    chromeUsage($media, CmsHeader::MORPH_ALIAS, 4242);

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)->toBe('cms_header #4242');
});
