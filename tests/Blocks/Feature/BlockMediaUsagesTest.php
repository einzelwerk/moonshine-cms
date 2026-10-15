<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\SharedBlockInUseException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\MediaGalleryBlock;
use Ewk\Cms\Media\Exceptions\MediaInUseException;
use Ewk\Cms\Media\Models\CmsMediaUsage;

beforeEach(function (): void {
    app(BlockRegistryInterface::class)->register(MediaGalleryBlock::class);
});

/**
 * @param  array<string, mixed>  $content
 */
function galleryBlock(array $content, int $ownerId = 1): CmsBlock
{
    return CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => $ownerId,
        'type' => MediaGalleryBlock::code(),
        'name' => 'Галерея',
        'content' => $content,
    ]);
}

/**
 * @return array<string, int>  dotted path => media id
 */
function usagesOf(CmsBlock $block): array
{
    /** @var array<string, int> $rows */
    $rows = CmsMediaUsage::query()
        ->where('usable_type', $block->getMorphClass())
        ->where('usable_id', $block->getKey())
        ->orderBy('path')
        ->pluck('cms_media_id', 'path')
        ->all();

    return $rows;
}

it('registers a usage for every media reference of the content, however deep', function (): void {
    $cover = Make::media('cover.jpg');
    $poster = Make::media('poster.jpg');
    $logo = Make::media('logo.png');
    $photo = Make::media('slide.jpg');

    $block = galleryBlock([
        'cover' => ['id' => $cover->uuid],
        'poster' => ['ru' => ['id' => $poster->uuid]],
        'brand' => ['logo' => ['id' => $logo->uuid]],
        'slides' => [
            ['caption' => 'Первый', 'photo' => ['id' => $photo->uuid]],
        ],
    ]);

    expect(usagesOf($block))->toBe([
        'brand.logo' => $logo->id,
        'cover' => $cover->id,
        'poster.ru' => $poster->id,
        'slides.0.photo' => $photo->id,
    ]);
});

it('finds the media of a repeater that is itself translatable', function (): void {
    $russian = Make::media('banner-ru.jpg');
    $english = Make::media('banner-en.jpg');

    // A translatable repeater looks exactly like a plain one from the outside:
    // only the schema says its rows sit behind a dictionary of languages.
    $block = galleryBlock([
        'promos' => [
            'ru' => [['caption' => 'Лето', 'banner' => ['id' => $russian->uuid]]],
            'en' => [['caption' => 'Summer', 'banner' => ['id' => $english->uuid]]],
        ],
    ]);

    expect(usagesOf($block))->toBe([
        'promos.en.0.banner' => $english->id,
        'promos.ru.0.banner' => $russian->id,
    ]);
});

it('refuses to delete a media record a block uses and allows it once the block is gone', function (): void {
    $cover = Make::media();

    $block = galleryBlock(['cover' => ['id' => $cover->uuid]]);

    expect(fn () => $cover->delete())->toThrow(MediaInUseException::class);

    $block->delete();

    expect(usagesOf($block))->toBe([])
        ->and($cover->fresh()?->delete())->toBeTrue();
});

it('deletes the blocks of an owner that is gone and frees what they used', function (): void {
    $cover = Make::media('cover.jpg');
    $inner = Make::media('inner.jpg');

    $owner = BlockOwner::query()->create(['is_active' => true]);

    $container = CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => $owner->getKey(),
        'type' => ColumnsBlock::code(),
        'variant' => '2',
        'name' => 'Колонки',
    ]);

    $root = galleryBlock(['cover' => ['id' => $cover->uuid]], (int) $owner->getKey());
    $child = galleryBlock(['cover' => ['id' => $inner->uuid]], (int) $owner->getKey());
    $child->parent_id = $container->getKey();
    $child->slot = 'col_1';
    $child->save();

    $owner->delete();

    expect(CmsBlock::query()->where('blockable_id', $owner->getKey())->count())->toBe(0)
        ->and(usagesOf($root))->toBe([])
        ->and(usagesOf($child))->toBe([])
        ->and($cover->fresh()?->delete())->toBeTrue()
        ->and($inner->fresh()?->delete())->toBeTrue();
});

it('keeps the blocks of a library entry that refuses to be deleted', function (): void {
    $cover = Make::media('cover.jpg');

    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо']);

    $block = CmsBlock::create([
        'blockable_type' => $shared->getMorphClass(),
        'blockable_id' => $shared->getKey(),
        'type' => MediaGalleryBlock::code(),
        'name' => 'Галерея',
        'content' => ['cover' => ['id' => $cover->uuid]],
    ]);

    CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => 1,
        'type' => SharedInsertionBlock::code(),
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
    ]);

    expect(fn () => $shared->delete())->toThrow(SharedBlockInUseException::class)
        ->and($block->fresh())->not->toBeNull()
        ->and(usagesOf($block))->toBe(['cover' => $cover->id]);
});

it('releases a reference the editor removed from the content', function (): void {
    $cover = Make::media('cover.jpg');
    $other = Make::media('other.jpg');

    $block = galleryBlock(['cover' => ['id' => $cover->uuid]]);

    $block->content = ['cover' => ['id' => $other->uuid]];
    $block->save();

    expect(usagesOf($block))->toBe(['cover' => $other->id])
        ->and($cover->fresh()?->delete())->toBeTrue()
        ->and(fn () => $other->delete())->toThrow(MediaInUseException::class);
});

it('writes no usage for a uuid no record answers', function (): void {
    $block = galleryBlock(['cover' => ['id' => '9f3c1a2b-4e55-4c1e-9a3f-0b71d2c35e10']]);

    expect(usagesOf($block))->toBe([]);
});

it('leaves the usages of a block of an unregistered type alone', function (): void {
    $cover = Make::media();

    $block = galleryBlock(['cover' => ['id' => $cover->uuid]]);

    $block->type = 'gallery_of_a_package_that_is_not_installed';
    $block->saveQuietly();
    $block->refresh();
    $block->save();

    expect(usagesOf($block))->toBe(['cover' => $cover->id]);
});
