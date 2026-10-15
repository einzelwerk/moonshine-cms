<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\MoveBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\BlockCycleException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockPlacementException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\RootOnlyBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\SingleSlotContainerBlock;
use Illuminate\Database\QueryException;

function moveBlock(): MoveBlock
{
    return app(MoveBlock::class);
}

/**
 * @return list<array{int|null, string|null, int}> parent id, slot, position
 */
function rootOrder(BlockOwner $owner): array
{
    return CmsBlock::query()
        ->where('blockable_id', $owner->getKey())
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->pluck('sort_order', 'name')
        ->all();
}

it('stores a child in a declared slot of a container', function (): void {
    $owner = Make::owner();
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);

    $child = Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_2']);

    expect($child->fresh()?->parent_id)->toBe($columns->id)
        ->and($child->fresh()?->slot)->toBe('col_2');
});

it('rejects a slot the variant of the container does not declare', function (): void {
    $owner = Make::owner();
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);

    expect(fn () => Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_3']))
        ->toThrow(InvalidBlockPlacementException::class, 'declares no slot [col_3]');
});

it('rejects a parent that is not a container', function (): void {
    $owner = Make::owner();
    $text = Make::block($owner);

    expect(fn () => Make::block($owner, ['parent_id' => $text->getKey(), 'slot' => 'col_1']))
        ->toThrow(InvalidBlockPlacementException::class, 'declares no slots');
});

it('rejects a root block carrying a slot and a nested block without one', function (): void {
    $owner = Make::owner();
    $columns = Make::block($owner, ['type' => 'columns', 'name' => 'Колонки']);

    expect(fn () => Make::block($owner, ['slot' => 'col_1']))
        ->toThrow(InvalidBlockPlacementException::class, 'without a parent')
        ->and(fn () => Make::block($owner, ['parent_id' => $columns->getKey()]))
        ->toThrow(InvalidBlockPlacementException::class, 'must name the slot');
});

it('rejects a parent belonging to another owner', function (): void {
    $owner = Make::owner();
    $other = Make::owner();
    $columns = Make::block($other, ['type' => 'columns', 'name' => 'Колонки']);

    expect(fn () => Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_1']))
        ->toThrow(InvalidBlockPlacementException::class, 'same owner');
});

it('rejects a type the slot does not accept, even when the type allows the parent', function (): void {
    app(BlockRegistryInterface::class)->register(SingleSlotContainerBlock::class);

    $owner = Make::owner();
    $container = Make::block($owner, ['type' => 'test_single_slot', 'name' => 'Контейнер']);

    expect(fn () => Make::block($owner, [
        'type' => 'columns',
        'parent_id' => $container->getKey(),
        'slot' => 'only',
    ]))->toThrow(InvalidBlockPlacementException::class, 'not allowed in slot [only]');
});

it('rejects a type that allows no container, even when the slot accepts anything', function (): void {
    app(BlockRegistryInterface::class)->register(RootOnlyBlock::class);

    $owner = Make::owner();
    $columns = Make::block($owner, ['type' => 'columns', 'name' => 'Колонки']);

    expect(fn () => Make::block($owner, [
        'type' => 'test_root_only',
        'parent_id' => $columns->getKey(),
        'slot' => 'col_1',
    ]))->toThrow(InvalidBlockPlacementException::class, 'not allowed inside [columns]');
});

it('rejects a child above the maximum of the slot', function (): void {
    app(BlockRegistryInterface::class)->register(SingleSlotContainerBlock::class);

    $owner = Make::owner();
    $container = Make::block($owner, ['type' => 'test_single_slot', 'name' => 'Контейнер']);

    Make::block($owner, ['parent_id' => $container->getKey(), 'slot' => 'only']);
    Make::block($owner, ['type' => 'toc', 'parent_id' => $container->getKey(), 'slot' => 'only']);

    expect(fn () => Make::block($owner, ['parent_id' => $container->getKey(), 'slot' => 'only']))
        ->toThrow(InvalidBlockPlacementException::class, 'at most 2 child block(s)');
});

it('rejects a repeated type in a slot that forbids one', function (): void {
    app(BlockRegistryInterface::class)->register(SingleSlotContainerBlock::class);

    $owner = Make::owner();
    $container = Make::block($owner, ['type' => 'test_single_slot', 'name' => 'Контейнер']);

    Make::block($owner, ['parent_id' => $container->getKey(), 'slot' => 'only']);

    expect(fn () => Make::block($owner, ['parent_id' => $container->getKey(), 'slot' => 'only']))
        ->toThrow(InvalidBlockPlacementException::class, 'already holds a block of type [text]');
});

it('allows three levels of blocks and rejects the fourth', function (): void {
    $owner = Make::owner();

    $level0 = Make::block($owner, ['type' => 'columns', 'name' => 'Уровень 0']);
    $level1 = Make::block($owner, [
        'type' => 'columns',
        'name' => 'Уровень 1',
        'parent_id' => $level0->getKey(),
        'slot' => 'col_1',
    ]);
    $level2 = Make::block($owner, [
        'type' => 'columns',
        'name' => 'Уровень 2',
        'parent_id' => $level1->getKey(),
        'slot' => 'col_1',
    ]);

    expect($level2->fresh()?->parent_id)->toBe($level1->id)
        ->and(fn () => Make::block($owner, ['parent_id' => $level2->getKey(), 'slot' => 'col_1']))
        ->toThrow(InvalidBlockPlacementException::class, 'exceeds the maximum level 2');
});

it('rejects moving a block into its own descendant', function (): void {
    $owner = Make::owner();

    $parent = Make::block($owner, ['type' => 'columns', 'name' => 'Родитель']);
    $child = Make::block($owner, [
        'type' => 'columns',
        'name' => 'Ребёнок',
        'parent_id' => $parent->getKey(),
        'slot' => 'col_1',
    ]);

    expect(fn () => moveBlock()->handle($parent, $child->getKey(), 'col_1', 0))
        ->toThrow(BlockCycleException::class)
        ->and($parent->fresh()?->parent_id)->toBeNull();
});

it('rejects a move that would push the descendants past the depth limit', function (): void {
    $owner = Make::owner();

    $target = Make::block($owner, ['type' => 'columns', 'name' => 'Цель']);
    $moved = Make::block($owner, ['type' => 'columns', 'name' => 'Переносимый']);
    $inner = Make::block($owner, [
        'type' => 'columns',
        'name' => 'Внутренний',
        'parent_id' => $moved->getKey(),
        'slot' => 'col_1',
    ]);
    Make::block($owner, ['parent_id' => $inner->getKey(), 'slot' => 'col_1']);

    expect(fn () => moveBlock()->handle($moved, $target->getKey(), 'col_1', 0))
        ->toThrow(InvalidBlockPlacementException::class, 'exceeds the maximum level 2')
        ->and($moved->fresh()?->parent_id)->toBeNull();
});

it('moves a block into a slot and keeps both groups densely ordered', function (): void {
    $owner = Make::owner();

    $first = Make::block($owner, ['name' => 'Первый', 'sort_order' => 0]);
    Make::block($owner, ['name' => 'Второй', 'sort_order' => 1]);
    $moved = Make::block($owner, ['name' => 'Третий', 'sort_order' => 2]);
    $columns = Make::block($owner, ['type' => 'columns', 'name' => 'Колонки', 'sort_order' => 3]);
    $resident = Make::block($owner, [
        'name' => 'Жилец',
        'parent_id' => $columns->getKey(),
        'slot' => 'col_1',
    ]);

    $result = moveBlock()->handle($moved, $columns->getKey(), 'col_1', 0);

    expect($result->parent_id)->toBe($columns->id)
        ->and($result->slot)->toBe('col_1')
        ->and($result->sort_order)->toBe(0)
        ->and($result->uuid)->toBe($moved->uuid)
        ->and($resident->fresh()?->sort_order)->toBe(1)
        ->and(rootOrder($owner))->toBe(['Первый' => 0, 'Второй' => 1, 'Колонки' => 2])
        ->and($first->fresh()?->sort_order)->toBe(0);
});

it('reorders a block inside its own group', function (): void {
    $owner = Make::owner();

    $first = Make::block($owner, ['name' => 'Первый', 'sort_order' => 0]);
    Make::block($owner, ['name' => 'Второй', 'sort_order' => 1]);
    Make::block($owner, ['name' => 'Третий', 'sort_order' => 2]);

    moveBlock()->handle($first, null, null, 2);

    expect(rootOrder($owner))->toBe(['Второй' => 0, 'Третий' => 1, 'Первый' => 2]);
});

it('writes nothing when the target of a move is invalid', function (): void {
    $owner = Make::owner();

    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    $block = Make::block($owner, ['name' => 'Блок', 'sort_order' => 1]);

    expect(fn () => moveBlock()->handle($block, $columns->getKey(), 'col_9', 0))
        ->toThrow(InvalidBlockPlacementException::class);

    $fresh = $block->fresh();

    expect($fresh?->parent_id)->toBeNull()
        ->and($fresh?->slot)->toBeNull()
        ->and($fresh?->sort_order)->toBe(1);
});

it('refuses to change the variant of a container while a disappearing slot is filled', function (): void {
    $owner = Make::owner();

    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '3', 'name' => 'Колонки']);
    Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_3']);

    $columns->variant = '2';

    expect(fn () => $columns->save())
        ->toThrow(InvalidBlockPlacementException::class, 'declares no slot [col_3], and that slot is not empty')
        ->and($columns->fresh()?->variant)->toBe('3');
});

it('allows changing the variant when the disappearing slots are empty', function (): void {
    $owner = Make::owner();

    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '3', 'name' => 'Колонки']);
    Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_1']);

    $columns->variant = '2';
    $columns->save();

    expect($columns->fresh()?->variant)->toBe('2');
});

it('refuses to delete a container while it still holds children', function (): void {
    $owner = Make::owner();

    $columns = Make::block($owner, ['type' => 'columns', 'name' => 'Колонки']);
    $child = Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_1']);

    // Deleting a parent together with its children is forbidden: removing a
    // subtree is an explicit operation of its own.
    expect(fn () => $columns->delete())->toThrow(QueryException::class);

    $child->delete();
    $columns->delete();

    expect(CmsBlock::query()->count())->toBe(0);
});
