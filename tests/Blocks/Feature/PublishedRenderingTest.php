<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;

/**
 * @return list<array<string, mixed>>|null
 */
function renderPublished(BlockOwner|CmsSharedBlock $owner): ?array
{
    return app(BlockRenderer::class)->renderPublished($owner, request());
}

it('gives nothing for an owner that was never published', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['content' => ['body' => ['ru' => '<p>Черновик</p>']]]);

    expect(renderPublished($owner))->toBeNull()
        ->and(app(BlockRenderer::class)->render($owner, request()))->toHaveCount(1);
});

it('keeps the published response unchanged while the draft is edited', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['content' => ['body' => ['ru' => '<p>Опубликовано</p>']]]);

    app(PublishOwner::class)->handle($owner);

    $block->content = ['body' => ['ru' => '<p>Черновик</p>']];
    $block->save();
    Make::block($owner, ['name' => 'Новый черновик', 'sort_order' => 1]);

    app()->setLocale('ru');
    $published = renderPublished($owner);

    expect($published)->toHaveCount(1)
        ->and($published[0]['uuid'])->toBe($block->uuid)
        ->and($published[0]['data']['body'])->toBe('<p>Опубликовано</p>')
        ->and(app(BlockRenderer::class)->render($owner, request()))->toHaveCount(2);
});

it('gives nothing once the owner is switched off and gives the snapshot back when it returns', function (): void {
    $owner = Make::owner();
    Make::block($owner);

    app(PublishOwner::class)->handle($owner);

    $owner->is_active = false;
    $owner->save();

    expect(renderPublished($owner))->toBeNull();

    $owner->is_active = true;
    $owner->save();

    expect(renderPublished($owner))->toHaveCount(1);
});

it('prunes a block that was switched off at publication time', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['name' => 'Видимый']);
    Make::block($owner, ['name' => 'Скрытый', 'is_active' => false, 'sort_order' => 1]);

    app(PublishOwner::class)->handle($owner);

    expect(renderPublished($owner))->toHaveCount(1);
});

it('renders the nesting of the snapshot with every declared slot', function (): void {
    $owner = Make::owner();

    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    Make::block($owner, [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_1',
        'name' => 'Левая',
        'anchor' => 'levaya',
        'content' => ['body' => ['ru' => '<p>Слева</p>']],
    ]);
    Make::block($owner, [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_2',
        'name' => 'Правая',
    ]);
    Make::block($owner, ['type' => 'toc', 'name' => 'Оглавление', 'sort_order' => 1]);

    app(PublishOwner::class)->handle($owner);

    app()->setLocale('ru');
    $published = renderPublished($owner);

    expect($published)->toHaveCount(2)
        ->and($published[0]['code'])->toBe('columns')
        ->and($published[0]['data']['widths'])->toBe([6, 6])
        ->and($published[0]['data']['collapse_on_mobile'])->toBeTrue()
        ->and(array_keys($published[0]['children']))->toBe(['col_1', 'col_2'])
        ->and($published[0]['children']['col_1'][0]['data']['body'])->toBe('<p>Слева</p>')
        // The table of contents collects the anchors of the whole owner,
        // nested blocks included.
        ->and($published[1]['data']['items'])->toBe([['anchor' => 'levaya', 'title' => 'Левая']]);
});

it('drops an insertion of a library entry that has never been published', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['name' => 'Свой блок']);

    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция']);
    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень',
        'content' => ['body' => ['ru' => '<p>Акция</p>']],
    ]);
    $inserted = Make::block($owner, [
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
        'sort_order' => 1,
    ]);

    app(PublishOwner::class)->handle($owner);

    expect(renderPublished($owner))->toHaveCount(1);

    // Publishing the library entry changes every page holding an insertion,
    // without republishing any of them.
    app(PublishOwner::class)->handle($shared);

    app()->setLocale('ru');
    $published = renderPublished($owner);

    expect($published)->toHaveCount(2)
        ->and($published[1]['uuid'])->toBe($inserted->uuid)
        ->and($published[1]['data']['body'])->toBe('<p>Акция</p>');
});

it('drops an insertion of a library entry that was switched off after publication', function (): void {
    $owner = Make::owner();

    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция']);
    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень',
    ]);
    Make::block($owner, [
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
    ]);

    app(PublishOwner::class)->handle($shared);
    app(PublishOwner::class)->handle($owner);

    expect(renderPublished($owner))->toHaveCount(1);

    $shared->is_active = false;
    $shared->save();

    expect(renderPublished($owner))->toBe([]);
});

it('shows the published state of a library entry, not its draft', function (): void {
    $owner = Make::owner();

    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция']);
    $root = CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень',
        'content' => ['body' => ['ru' => '<p>Опубликовано</p>']],
    ]);
    Make::block($owner, [
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
    ]);

    app(PublishOwner::class)->handle($shared);
    app(PublishOwner::class)->handle($owner);

    $root->content = ['body' => ['ru' => '<p>Черновик</p>']];
    $root->save();

    app()->setLocale('ru');
    $published = renderPublished($owner);

    expect($published[0]['data']['body'])->toBe('<p>Опубликовано</p>');
});
