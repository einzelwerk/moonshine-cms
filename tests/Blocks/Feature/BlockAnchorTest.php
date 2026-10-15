<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Tests\Fixtures\CatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\FeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;

beforeEach(function (): void {
    $registry = app(BlockRegistryInterface::class);

    $registry->register(CatalogBlock::class);
    $registry->register(FeedBlock::class);
});

it('numbers the next anchor of the same type within the owner', function (): void {
    $first = Make::block(attributes: ['type' => 'catalog']);
    $second = Make::block(attributes: ['type' => 'catalog', 'sort_order' => 1]);
    $third = Make::block(attributes: ['type' => 'catalog', 'sort_order' => 2]);

    expect([$first->anchor, $second->anchor, $third->anchor])
        ->toBe(['catalog', 'catalog-2', 'catalog-3']);
});

it('counts the collision only within the owner', function (): void {
    Make::block(attributes: ['type' => 'catalog']);
    $other = Make::block(attributes: ['type' => 'catalog', 'blockable_id' => 2]);

    expect($other->anchor)->toBe('catalog');
});

it('steps over an anchor the editor typed himself', function (): void {
    Make::block(attributes: ['type' => 'catalog', 'anchor' => 'catalog-2']);
    $generated = Make::block(attributes: ['type' => 'catalog', 'sort_order' => 1]);
    $next = Make::block(attributes: ['type' => 'catalog', 'sort_order' => 2]);

    expect($generated->anchor)->toBe('catalog')
        ->and($next->anchor)->toBe('catalog-3');
});

it('keeps the anchor the editor typed', function (): void {
    expect(Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha'])->anchor)->toBe('afisha');
});

it('leaves a block that is not dynamic without an anchor', function (): void {
    expect(Make::block(attributes: ['type' => 'text'])->anchor)->toBeNull()
        ->and(Make::block(attributes: ['type' => 'columns'])->anchor)->toBeNull();
});

it('leaves an unregistered type without an anchor', function (): void {
    expect(Make::block(attributes: ['type' => 'nonexistent'])->anchor)->toBeNull();
});

it('does not renumber the anchor of a block that already has one on a later save', function (): void {
    $block = Make::block(attributes: ['type' => 'catalog']);
    $block->name = 'Переименован';
    $block->save();

    expect($block->fresh()?->anchor)->toBe('catalog');
});

it('generates an anchor for a dynamic block of another type independently', function (): void {
    Make::block(attributes: ['type' => 'catalog']);
    $feed = Make::block(attributes: ['type' => 'feed', 'sort_order' => 1]);

    expect($feed->anchor)->toBe('feed');
});
