<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\MoveBlock;
use Ewk\Cms\Blocks\Actions\UnlinkSharedBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Illuminate\Support\Carbon;

/**
 * The mark of change of an owner: the four statuses are computed out of the
 * snapshot and this mark, so every operation over a block has to move it —
 * otherwise an owner keeps saying «опубликовано» after a block of it was
 * deleted.
 */
function touchableOwner(int $id = 1): BlockOwner
{
    $owner = BlockOwner::query()->create(['id' => $id, 'is_active' => true]);

    BlockOwner::query()->whereKey($owner->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);

    return $owner->refresh();
}

function markOf(BlockOwner $owner): ?Carbon
{
    return $owner->fresh()?->updated_at;
}

it('moves the mark of the owner when a block is created', function (): void {
    $owner = touchableOwner();
    $before = markOf($owner);

    Make::block($owner);

    expect(markOf($owner))->toBeGreaterThan($before);
});

it('moves the mark of the owner when a block is edited', function (): void {
    $owner = touchableOwner();
    $block = Make::block($owner);

    BlockOwner::query()->whereKey($owner->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);
    $before = markOf($owner);

    $block->name = 'Другое имя';
    $block->save();

    expect(markOf($owner))->toBeGreaterThan($before);
});

it('moves the mark of the owner when a block is moved', function (): void {
    $owner = touchableOwner();
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    $text = Make::block($owner, ['sort_order' => 1]);

    BlockOwner::query()->whereKey($owner->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);
    $before = markOf($owner);

    app(MoveBlock::class)->handle($text, $columns->id, 'col_1');

    expect(markOf($owner))->toBeGreaterThan($before);
});

it('moves the mark of the owner when a block is deleted', function (): void {
    $owner = touchableOwner();
    $block = Make::block($owner);

    BlockOwner::query()->whereKey($owner->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);
    $before = markOf($owner);

    $block->delete();

    expect(markOf($owner))->toBeGreaterThan($before);
});

it('moves the mark of every owner an unlinked insertion belonged to', function (): void {
    $library = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);

    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $library->getKey(),
        'type' => 'text',
        'name' => 'Текст общего блока',
    ]);

    $first = touchableOwner(1);
    $second = touchableOwner(2);

    foreach ([$first, $second] as $owner) {
        Make::block($owner, ['type' => SharedInsertionBlock::code(), 'cms_shared_block_id' => $library->getKey()]);
    }

    foreach ([$first, $second] as $owner) {
        BlockOwner::query()->whereKey($owner->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);
    }

    $removed = app(UnlinkSharedBlock::class)->handle($library);

    expect($removed)->toBe(2)
        ->and(markOf($first))->toBeGreaterThan(Carbon::parse('2020-01-01 00:00:00'))
        ->and(markOf($second))->toBeGreaterThan(Carbon::parse('2020-01-01 00:00:00'));
});

it('moves the mark of the library entry when one of its own blocks changes', function (): void {
    $library = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);

    CmsSharedBlock::query()->whereKey($library->getKey())->update(['updated_at' => Carbon::parse('2020-01-01 00:00:00')]);
    $before = $library->fresh()?->updated_at;

    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $library->getKey(),
        'type' => 'text',
        'name' => 'Текст общего блока',
    ]);

    expect($library->fresh()?->updated_at)->toBeGreaterThan($before);
});
