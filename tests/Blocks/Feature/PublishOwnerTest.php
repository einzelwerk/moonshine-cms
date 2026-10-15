<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Exceptions\PublicationRejectedException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\RequiringOwner;

/**
 * @param  list<string>  $required
 */
function requiringOwner(array $required = []): RequiringOwner
{
    $owner = RequiringOwner::query()->create(['is_active' => true]);
    $owner->required = $required;

    return $owner;
}

function publish(RequiringOwner $owner, ?string $note = null, ?int $authorId = null): CmsVersion
{
    return app(PublishOwner::class)->handle($owner, $note, $authorId);
}

it('keeps one row per owner and raises its number on every publication', function (): void {
    $owner = requiringOwner();
    Make::block($owner);

    $first = publish($owner);
    $second = publish($owner);

    expect(CmsVersion::query()->count())->toBe(1)
        ->and($second->getKey())->toBe($first->getKey())
        ->and($first->number)->toBe(1)
        ->and($second->number)->toBe(2);
});

it('snapshots the settings of the tree, the owner and the catalog of languages', function (): void {
    $owner = requiringOwner();

    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    Make::block($owner, [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_1',
        'name' => 'Левая',
        'content' => ['body' => ['ru' => '<p>Слева</p>']],
    ]);
    Make::block($owner, [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_2',
        'name' => 'Правая',
    ]);

    $version = publish($owner, note: 'Первый выкат', authorId: 7);
    $snapshot = $version->snapshot;
    $blocks = $version->snapshotBlocks();

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('columns')
        ->and($blocks[0]['variant'])->toBe('2')
        ->and($blocks[0]['children']['col_1'][0]['content'])->toBe(['body' => ['ru' => '<p>Слева</p>']])
        ->and($blocks[0]['children']['col_2'][0]['name'])->toBe('Правая')
        ->and($snapshot['owner']['id'])->toBe($owner->getKey())
        ->and($version->locales)->toBe(['ru', 'en'])
        ->and($version->note)->toBe('Первый выкат')
        ->and($version->author_id)->toBe(7)
        ->and($version->published_at)->not->toBeNull();
});

it('snapshots the reference of an insertion instead of the content of the original', function (): void {
    $owner = requiringOwner();

    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Акция']);
    CmsBlock::create([
        'blockable_type' => CmsSharedBlock::MORPH_ALIAS,
        'blockable_id' => $shared->getKey(),
        'type' => 'text',
        'name' => 'Корень',
        'content' => ['body' => ['ru' => '<p>Акция</p>']],
    ]);

    Make::block($owner, [
        'type' => 'shared',
        'name' => 'Вставка',
        'cms_shared_block_id' => $shared->getKey(),
    ]);

    $blocks = publish($owner)->snapshotBlocks();

    expect($blocks[0]['type'])->toBe('shared')
        ->and($blocks[0]['cms_shared_block_id'])->toBe($shared->getKey())
        ->and($blocks[0]['content'])->toBeNull();
});

it('keeps a switched off row in the snapshot', function (): void {
    $owner = requiringOwner();
    Make::block($owner, ['name' => 'Видимый']);
    Make::block($owner, ['name' => 'Скрытый', 'is_active' => false]);

    $blocks = publish($owner)->snapshotBlocks();

    expect($blocks)->toHaveCount(2)
        ->and($blocks[1]['is_active'])->toBeFalse();
});

it('refuses a publication that misses a required block and writes nothing', function (): void {
    $owner = requiringOwner(['text', 'toc']);
    Make::block($owner);

    try {
        publish($owner);
        $this->fail('Publication should have been rejected.');
    } catch (PublicationRejectedException $rejected) {
        expect($rejected->problems)->toBe(['Required block [toc] is missing or switched off.'])
            ->and(CmsVersion::query()->count())->toBe(0);
    }
});

it('treats a switched off required block as missing', function (): void {
    $owner = requiringOwner(['text']);
    Make::block($owner, ['is_active' => false]);

    expect(fn () => publish($owner))
        ->toThrow(PublicationRejectedException::class, 'Required block [text] is missing or switched off');
});

it('refuses a publication with an unmet slot minimum', function (): void {
    $owner = requiringOwner();
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_1']);

    expect(fn () => publish($owner))
        ->toThrow(PublicationRejectedException::class, 'Slot [col_2]')
        ->and(CmsVersion::query()->count())->toBe(0);
});

it('ignores the slots of a switched off container', function (): void {
    $owner = requiringOwner();
    Make::block($owner, ['type' => 'columns', 'name' => 'Колонки', 'is_active' => false]);

    expect(publish($owner)->number)->toBe(1);
});

it('refuses a publication with a block of an unregistered type', function (): void {
    $owner = requiringOwner();
    $block = Make::block($owner);
    CmsBlock::query()->whereKey($block->getKey())->update(['type' => 'gone']);

    expect(fn () => publish($owner))
        ->toThrow(PublicationRejectedException::class, 'Block type [gone] is not registered')
        ->and(CmsVersion::query()->count())->toBe(0);
});

it('reports every problem of a failed publication at once', function (): void {
    $owner = requiringOwner(['toc']);
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2', 'name' => 'Колонки']);
    Make::block($owner, ['parent_id' => $columns->getKey(), 'slot' => 'col_1']);

    try {
        publish($owner);
        $this->fail('Publication should have been rejected.');
    } catch (PublicationRejectedException $rejected) {
        expect($rejected->problems)->toHaveCount(2);
    }
});
