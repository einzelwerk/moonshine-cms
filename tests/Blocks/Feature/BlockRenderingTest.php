<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Events\UnknownBlockTypeEncountered;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\OptionalBlock;
use Illuminate\Support\Facades\Event;

function blockOwner(int $id = 1): BlockOwner
{
    $owner = new BlockOwner();
    $owner->id = $id;

    return $owner;
}

function renderOwner(BlockOwner $owner): array
{
    return app(BlockRenderer::class)->render($owner, request());
}

it('wraps a block into the envelope of the api contract', function (): void {
    $block = Make::block(attributes: [
        'variant' => 'narrow',
        'anchor' => 'about',
        'content' => [
            'title' => ['ru' => 'О компании', 'en' => 'About us'],
            'body' => ['ru' => '<p>Текст</p>', 'en' => '<p>Text</p>'],
        ],
    ]);

    app()->setLocale('ru');
    $rendered = renderOwner(blockOwner());

    expect($rendered)->toHaveCount(1)
        ->and(array_keys($rendered[0]))->toBe(['uuid', 'code', 'variant', 'anchor', 'data', 'children'])
        ->and($rendered[0]['uuid'])->toBe($block->uuid)
        ->and($rendered[0]['uuid'])->not->toBe((string) $block->id)
        ->and($rendered[0]['code'])->toBe('text')
        ->and($rendered[0]['variant'])->toBe('narrow')
        ->and($rendered[0]['anchor'])->toBe('about')
        // An empty map of slots and not an empty array: `children` travels
        // to the frontend as an object.
        ->and($rendered[0]['children'])->toEqual(new stdClass())
        ->and($rendered[0]['data'])->toBe(['title' => 'О компании', 'body' => '<p>Текст</p>']);
});

it('resolves translatable values into the requested language', function (): void {
    Make::block(attributes: [
        'content' => [
            'title' => ['ru' => 'О компании', 'en' => 'About us'],
            'body' => ['ru' => '<p>Текст</p>', 'en' => '<p>Text</p>'],
        ],
    ]);

    app()->setLocale('en');

    expect(renderOwner(blockOwner())[0]['data'])->toBe(['title' => 'About us', 'body' => '<p>Text</p>']);
});

it('falls back to the default language for a missing translation', function (): void {
    Make::block(attributes: [
        'content' => [
            'title' => ['ru' => 'О компании'],
            'body' => ['ru' => '<p>Текст</p>', 'en' => ''],
        ],
    ]);

    app()->setLocale('en');

    expect(renderOwner(blockOwner())[0]['data'])->toBe(['title' => 'О компании', 'body' => '<p>Текст</p>']);
});

it('returns the blocks of the owner in editor order and skips the switched off ones', function (): void {
    Make::block(attributes: ['name' => 'Второй', 'sort_order' => 2, 'anchor' => 'second']);
    Make::block(attributes: ['name' => 'Первый', 'sort_order' => 1, 'anchor' => 'first']);
    Make::block(attributes: ['name' => 'Выключенный', 'sort_order' => 0, 'is_active' => false, 'anchor' => 'off']);

    expect(array_column(renderOwner(blockOwner()), 'anchor'))->toBe(['first', 'second']);
});

it('does not render the blocks of another owner', function (): void {
    Make::block(attributes: ['anchor' => 'mine']);
    CmsBlock::create([
        'blockable_type' => BlockOwner::class,
        'blockable_id' => 2,
        'type' => 'text',
        'name' => 'Чужой',
        'anchor' => 'theirs',
    ]);

    expect(array_column(renderOwner(blockOwner()), 'anchor'))->toBe(['mine']);
});

it('excludes an unregistered block type from the response and reports it', function (): void {
    Make::block(attributes: ['anchor' => 'known']);
    $unknown = Make::block(attributes: ['type' => 'nonexistent', 'anchor' => 'unknown', 'sort_order' => 1]);

    Event::fake([UnknownBlockTypeEncountered::class]);

    $rendered = renderOwner(blockOwner());

    expect(array_column($rendered, 'code'))->toBe(['text'])
        ->and(array_column($rendered, 'anchor'))->not->toContain('unknown');

    Event::assertDispatched(
        UnknownBlockTypeEncountered::class,
        fn (UnknownBlockTypeEncountered $event): bool => $event->uuid === $unknown->uuid
            && $event->type === 'nonexistent'
            && $event->blockableType === BlockOwner::class
            && $event->blockableId === 1,
    );
});

it('leaves out a block whose resource has nothing to show', function (): void {
    app(BlockRegistryInterface::class)->register(OptionalBlock::class);

    Make::block(attributes: ['type' => 'optional', 'anchor' => 'empty', 'content' => ['caption' => '']]);
    Make::block(attributes: ['type' => 'optional', 'anchor' => 'filled', 'sort_order' => 1, 'content' => ['caption' => 'Подпись']]);
    Make::block(attributes: ['anchor' => 'text', 'sort_order' => 2]);

    expect(array_column(renderOwner(blockOwner()), 'anchor'))->toBe(['filled', 'text']);
});

it('collects the anchors of the whole owner into the table of contents, but not its own', function (): void {
    Make::block(attributes: [
        'type' => 'toc',
        'name' => 'Оглавление',
        'anchor' => 'nav',
        'sort_order' => 0,
        'content' => ['title' => ['ru' => 'На этой странице']],
    ]);
    Make::block(attributes: [
        'anchor' => 'about',
        'name' => 'Про нас',
        'sort_order' => 1,
        'content' => ['title' => ['ru' => 'О компании'], 'body' => ['ru' => '<p>a</p>']],
    ]);
    Make::block(attributes: [
        'anchor' => 'team',
        'name' => 'Команда',
        'sort_order' => 2,
        'content' => ['title' => ['ru' => 'Наша команда'], 'body' => ['ru' => '<p>b</p>']],
    ]);
    Make::block(attributes: [
        'anchor' => 'contacts',
        'name' => 'Контакты',
        'sort_order' => 3,
        'content' => ['body' => ['ru' => '<p>c</p>']],
    ]);

    app()->setLocale('ru');
    $rendered = renderOwner(blockOwner());

    expect($rendered)->toHaveCount(4)
        ->and($rendered[0]['code'])->toBe('toc')
        ->and($rendered[0]['data'])->toBe(['title' => 'На этой странице', 'items' => [
            ['anchor' => 'about', 'title' => 'О компании'],
            ['anchor' => 'team', 'title' => 'Наша команда'],
            ['anchor' => 'contacts', 'title' => 'Контакты'],
        ]]);
});

it('leaves the table of contents empty when no sibling carries an anchor', function (): void {
    Make::block(attributes: ['type' => 'toc', 'name' => 'Оглавление']);
    Make::block(attributes: ['name' => 'Без якоря', 'sort_order' => 1, 'content' => ['body' => ['ru' => '<p>a</p>']]]);

    // An empty heading is the default caption on the side of the frontend.
    expect(renderOwner(blockOwner())[0]['data'])->toBe(['title' => '', 'items' => []]);
});

it('presents the children of a container as a map of every declared slot', function (): void {
    $columns = Make::block(attributes: ['type' => 'columns', 'variant' => '1-2', 'name' => 'Колонки']);
    Make::block(attributes: [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_2',
        'name' => 'Широкая',
        'content' => ['body' => ['ru' => '<p>Справа</p>']],
    ]);
    Make::block(attributes: [
        'parent_id' => $columns->getKey(),
        'slot' => 'col_2',
        'name' => 'Скрытая',
        'is_active' => false,
        'sort_order' => 1,
    ]);

    app()->setLocale('ru');
    $rendered = renderOwner(blockOwner());

    expect($rendered)->toHaveCount(1)
        ->and($rendered[0]['data']['widths'])->toBe([4, 8])
        // Every declared slot is present, the empty ones included, in the
        // order of the schema.
        ->and(array_keys($rendered[0]['children']))->toBe(['col_1', 'col_2'])
        ->and($rendered[0]['children']['col_1'])->toBe([])
        ->and($rendered[0]['children']['col_2'])->toHaveCount(1)
        ->and($rendered[0]['children']['col_2'][0]['data']['body'])->toBe('<p>Справа</p>');
});

it('exposes the blocks of an owner through the trait in editor order', function (): void {
    Make::block(attributes: ['name' => 'Второй', 'sort_order' => 2]);
    Make::block(attributes: ['name' => 'Первый', 'sort_order' => 1]);

    expect(blockOwner()->blocks()->pluck('name')->all())->toBe(['Первый', 'Второй']);
});

it('generates a uuid on create and keeps it across updates', function (): void {
    $block = Make::block(attributes: []);
    $uuid = $block->uuid;

    $block->update(['sort_order' => 5]);

    expect($uuid)->not->toBeEmpty()
        ->and($block->fresh()?->uuid)->toBe($uuid);
});
