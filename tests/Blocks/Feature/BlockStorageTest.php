<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keeps an anchor unique within its owner', function (): void {
    Make::block(attributes: ['anchor' => 'about']);

    expect(fn () => Make::block(attributes: ['anchor' => 'about']))->toThrow(QueryException::class);
});

it('allows the same anchor under a different owner', function (): void {
    Make::block(attributes: ['anchor' => 'about']);
    $other = Make::block(attributes: ['blockable_id' => 2, 'anchor' => 'about']);

    expect($other->exists)->toBeTrue();
});

it('allows many blocks of one owner without an anchor', function (): void {
    Make::block();
    Make::block();
    Make::block();

    expect(CmsBlock::query()->whereNull('anchor')->count())->toBe(3);
});

it('rejects a reference to a shared block that does not exist', function (): void {
    expect(fn () => DB::table('cms_blocks')->insert([
        'uuid' => (string) Str::uuid(),
        'blockable_type' => BlockOwner::class,
        'blockable_id' => 1,
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => 4242,
    ]))->toThrow(QueryException::class);
});

it('hard deletes a block instead of hiding it', function (): void {
    $block = Make::block(attributes: ['anchor' => 'about']);

    $block->delete();

    expect(CmsBlock::query()->count())->toBe(0)
        ->and(Make::block(attributes: ['anchor' => 'about'])->exists)->toBeTrue();
});
