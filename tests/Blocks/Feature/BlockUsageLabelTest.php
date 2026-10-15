<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\MediaUsages;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Media\Usages\UsageLabelResolver;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;
use Illuminate\Support\Facades\DB;

function usageOfBlock(CmsMedia $media, int|string $blockId, string $path = 'cover'): CmsMediaUsage
{
    return CmsMediaUsage::query()->create([
        'cms_media_id' => $media->id,
        'usable_type' => CmsBlock::MORPH_ALIAS,
        'usable_id' => $blockId,
        'field' => CmsBlock::MEDIA_USAGE_FIELD,
        'path' => $path,
    ]);
}

it('names the library entry a block belongs to and not the block', function (): void {
    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция недели']);
    $block = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень общего блока',
    ]);

    $media = Make::media();
    usageOfBlock($media, $block->getKey());

    $groups = app(MediaUsages::class)->of($media);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->owner->title)->toBe('Акция недели')
        ->and($groups[0]->owner->type)->toBe('shared_block')
        ->and($groups[0]->owner->ownerType)->toBe(CmsSharedBlock::MORPH_ALIAS)
        ->and($groups[0]->owner->ownerId)->toEqual($shared->getKey())
        ->and($groups[0]->owner->url)->toBeNull()
        ->and($groups[0]->fields())->toBe(['cover'])
        ->and($groups[0]->sources)->toHaveCount(1)
        ->and($groups[0]->sources[0]->type)->toBe(CmsBlock::MORPH_ALIAS)
        ->and($groups[0]->sources[0]->id)->toEqual($block->getKey());
});

it('hands the owner of any other kind over to whoever registered it', function (): void {
    app(UsageLabelRegistry::class)->register(new class () implements UsageLabelResolver {
        public function supports(string $usableType): bool
        {
            return $usableType === BlockOwner::class;
        }

        public function resolve(CmsMediaUsage $usage): ?UsageOwnerLabel
        {
            return new UsageOwnerLabel('Главная страница', null, 'page', $usage->usable_type, $usage->usable_id);
        }
    });

    $owner = BlockOwner::query()->create(['is_active' => true]);
    $block = CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => $owner->getKey(),
        'type' => 'text',
        'name' => 'Блок страницы',
    ]);

    $media = Make::media();
    usageOfBlock($media, $block->getKey(), 'items.0.image');

    $label = app(MediaUsages::class)->of($media)[0]->owner;

    expect($label->title)->toBe('Главная страница')
        ->and($label->type)->toBe('page')
        ->and($label->ownerId)->toEqual($owner->getKey());
});

it('names the owner and not the block when nobody registered that owner', function (): void {
    $owner = BlockOwner::query()->create(['is_active' => true]);
    $block = CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => $owner->getKey(),
        'type' => 'text',
        'name' => 'Блок страницы',
    ]);

    $media = Make::media();
    usageOfBlock($media, $block->getKey());

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)
        ->toBe(BlockOwner::class.' #'.$owner->getKey());
});

it('falls back to the block itself when the block is gone', function (): void {
    $media = Make::media();
    usageOfBlock($media, 4242);

    expect(app(MediaUsages::class)->of($media)[0]->owner->title)->toBe('cms_block #4242');
});

it('groups the two pictures of one block under one owner', function (): void {
    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция']);
    $block = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень',
    ]);

    $media = Make::media();
    usageOfBlock($media, $block->getKey(), 'items.0.image');
    usageOfBlock($media, $block->getKey(), 'items.1.image');

    $groups = app(MediaUsages::class)->of($media);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->sources)->toHaveCount(1)
        ->and($groups[0]->fields())->toBe(['items.0.image', 'items.1.image']);
});

it('puts two blocks of one library entry under one group with two sources', function (): void {
    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция недели']);

    $first = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Первый',
    ]);
    $second = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Второй',
    ]);

    $media = Make::media();
    usageOfBlock($media, $first->getKey(), 'cover');
    usageOfBlock($media, $second->getKey(), 'items.0.image');

    $groups = app(MediaUsages::class)->of($media);

    // One entry to open, two blocks inside it to scroll to.
    expect($groups)->toHaveCount(1)
        ->and($groups[0]->owner->title)->toBe('Акция недели')
        ->and($groups[0]->sources)->toHaveCount(2)
        ->and($groups[0]->sources[0]->id)->toEqual($first->getKey())
        ->and($groups[0]->sources[0]->fields)->toBe(['cover'])
        ->and($groups[0]->sources[1]->id)->toEqual($second->getKey())
        ->and($groups[0]->sources[1]->fields)->toBe(['items.0.image'])
        ->and($groups[0]->fields())->toBe(['cover', 'items.0.image']);
});

it('reads the entries of a whole listing in a handful of queries', function (): void {
    $entries = [];

    for ($owner = 0; $owner < 2; $owner++) {
        $shared = CmsSharedBlock::query()->create(['code' => 'promo-'.$owner, 'name' => 'Акция '.$owner]);

        $entries[] = CmsBlock::create([
            'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
            'blockable_id' => $shared->getKey(),
            'type' => 'text',
            'name' => 'Корень '.$owner,
        ]);
    }

    $media = Make::media();

    foreach ($entries as $block) {
        for ($i = 0; $i < 10; $i++) {
            usageOfBlock($media, $block->getKey(), 'items.'.$i.'.image');
        }
    }

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $groups = app(MediaUsages::class)->of($media);

    $queries = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    // Three: the rows, the blocks behind them, the entries above those.
    // Without the memo and the eager load it would be twenty two.
    expect($groups)->toHaveCount(2)
        ->and($queries)->toBeLessThanOrEqual(6);
});
