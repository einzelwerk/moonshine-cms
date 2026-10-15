<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\UnlinkSharedBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockPlacementException;
use Ewk\Cms\Blocks\Exceptions\SharedBlockInUseException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\SingleSlotContainerBlock;

/**
 * A library entry with a single root block of the given type.
 *
 * @param  array<string, mixed>  $rootAttributes
 */
function sharedEntry(string $code, array $rootAttributes = [], bool $active = true): CmsSharedBlock
{
    $shared = CmsSharedBlock::query()->create([
        'code' => $code,
        'name' => 'Общий блок '.$code,
        'is_active' => $active,
    ]);

    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень общего блока',
        'content' => ['title' => ['ru' => 'Акция'], 'body' => ['ru' => '<p>Минус десять</p>']],
        ...$rootAttributes,
    ]);

    return $shared;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertion(BlockOwner $owner, CmsSharedBlock $shared, array $attributes = []): CmsBlock
{
    return Make::block($owner, [
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
        ...$attributes,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function renderDraft(BlockOwner $owner): array
{
    return app(BlockRenderer::class)->render($owner, request());
}

it('renders the tree of the original in place of the insertion', function (): void {
    $owner = Make::owner();
    $shared = sharedEntry('promo-10-percent', ['variant' => 'narrow']);
    $inserted = insertion($owner, $shared, ['anchor' => 'promo']);

    app()->setLocale('ru');
    $rendered = renderDraft($owner);

    expect($rendered)->toHaveCount(1)
        ->and($rendered[0]['code'])->toBe('text')
        ->and($rendered[0]['variant'])->toBe('narrow')
        ->and($rendered[0]['data']['body'])->toBe('<p>Минус десять</p>')
        // The uuid of the envelope is the uuid of the insertion, so two
        // placements of one banner stay addressable apart.
        ->and($rendered[0]['uuid'])->toBe($inserted->uuid)
        ->and($rendered[0]['uuid'])->not->toBe($shared->rootBlock()?->uuid)
        ->and($rendered[0]['anchor'])->toBe('promo');
});

it('renders the children of a shared container in place of the insertion', function (): void {
    $owner = Make::owner();

    $shared = CmsSharedBlock::query()->create(['code' => 'promo-columns', 'name' => 'Две колонки']);
    $root = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'columns',
        'variant' => '2',
        'name' => 'Колонки',
    ]);
    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'parent_id' => $root->getKey(),
        'slot' => 'col_1',
        'type' => 'text',
        'name' => 'Левая',
        'content' => ['body' => ['ru' => '<p>Слева</p>']],
    ]);

    $inserted = insertion($owner, $shared);

    app()->setLocale('ru');
    $rendered = renderDraft($owner);

    expect($rendered[0]['uuid'])->toBe($inserted->uuid)
        ->and($rendered[0]['code'])->toBe('columns')
        ->and($rendered[0]['data']['widths'])->toBe([6, 6])
        ->and($rendered[0]['children']['col_1'][0]['data']['body'])->toBe('<p>Слева</p>')
        ->and($rendered[0]['children']['col_2'])->toBe([]);
});

it('combines the activity of the insertion and the original through and', function (): void {
    $owner = Make::owner();

    $active = sharedEntry('active-one');
    $switchedOff = sharedEntry('switched-off', active: false);

    insertion($owner, $active, ['sort_order' => 0]);
    insertion($owner, $switchedOff, ['sort_order' => 1]);
    insertion($owner, $active, ['sort_order' => 2, 'is_active' => false]);

    expect(renderDraft($owner))->toHaveCount(1);
});

it('drops an insertion of an empty library entry from the response', function (): void {
    $owner = Make::owner();

    $empty = CmsSharedBlock::query()->create(['code' => 'empty', 'name' => 'Пустой']);
    insertion($owner, $empty);

    expect(renderDraft($owner))->toBe([]);
});

it('refuses to delete a library entry while it is inserted somewhere', function (): void {
    $owner = Make::owner();
    $shared = sharedEntry('promo');
    insertion($owner, $shared);

    expect(fn () => $shared->delete())
        ->toThrow(SharedBlockInUseException::class, 'cannot be deleted while it has 1 insertion(s)')
        ->and(CmsSharedBlock::query()->count())->toBe(1);
});

it('unlinks every insertion without touching the library entry or its content', function (): void {
    $owner = Make::owner();
    $other = Make::owner();
    $shared = sharedEntry('promo');

    insertion($owner, $shared);
    insertion($other, $shared);
    $ownBlock = Make::block($owner, ['name' => 'Свой блок']);

    $unlinked = app(UnlinkSharedBlock::class)->handle($shared);

    expect($unlinked)->toBe(2)
        ->and($shared->fresh())->not->toBeNull()
        ->and($shared->rootBlock())->not->toBeNull()
        ->and(CmsBlock::query()->whereKey($ownBlock->getKey())->exists())->toBeTrue();

    $shared->delete();

    expect(CmsSharedBlock::query()->count())->toBe(0);
});

it('checks the nesting restrictions against the type of the original', function (): void {
    app(BlockRegistryInterface::class)->register(SingleSlotContainerBlock::class);

    $owner = Make::owner();
    $container = Make::block($owner, ['type' => 'test_single_slot', 'name' => 'Контейнер']);

    $text = sharedEntry('promo-text');
    $columns = sharedEntry('promo-columns', ['type' => 'columns', 'variant' => '2']);

    $accepted = insertion($owner, $text, [
        'parent_id' => $container->getKey(),
        'slot' => 'only',
    ]);

    expect($accepted->fresh()?->slot)->toBe('only')
        ->and(fn () => insertion($owner, $columns, [
            'parent_id' => $container->getKey(),
            'slot' => 'only',
        ]))->toThrow(InvalidBlockPlacementException::class, 'Block type [columns] is not allowed in slot [only]');
});

it('forbids an insertion inside the library itself', function (): void {
    $shared = sharedEntry('promo');
    $target = sharedEntry('holder');

    expect(fn () => CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $target->getKey(),
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
    ]))->toThrow(InvalidBlockPlacementException::class, 'cannot hold an insertion');
});

it('refuses a reference on a block that is not an insertion and an insertion without one', function (): void {
    $owner = Make::owner();
    $shared = sharedEntry('promo');

    expect(fn () => Make::block($owner, ['cms_shared_block_id' => $shared->getKey()]))
        ->toThrow(InvalidBlockPlacementException::class, 'Only a block of type [shared]')
        ->and(fn () => Make::block($owner, ['type' => 'shared', 'name' => 'Вставка']))
        ->toThrow(InvalidBlockPlacementException::class, 'must reference a shared block');
});
