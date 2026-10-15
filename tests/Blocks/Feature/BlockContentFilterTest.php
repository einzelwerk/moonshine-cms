<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;

it('keeps only the keys declared by the block type when writing', function (): void {
    $block = Make::block(attributes: [
        'content' => [
            'title' => ['ru' => 'О компании'],
            'body' => ['ru' => '<p>Текст</p>'],
            'bogus_field' => 'осталось от другого типа',
        ],
    ]);

    expect($block->fresh()?->content)->toBe([
        'title' => ['ru' => 'О компании'],
        'body' => ['ru' => '<p>Текст</p>'],
    ]);
});

it('filters the content of an update as well', function (): void {
    $block = Make::block(attributes: ['content' => ['body' => ['ru' => '<p>Текст</p>']]]);

    $block->update(['content' => ['body' => ['ru' => '<p>Новый</p>'], 'bogus_field' => 1]]);

    expect($block->fresh()?->content)->toBe(['body' => ['ru' => '<p>Новый</p>']]);
});

it('leaves the content of an unregistered type alone, so seeded data survives', function (): void {
    $block = Make::block(attributes: [
        'type' => 'nonexistent',
        'content' => ['anything' => 'kept', 'body' => ['ru' => 'kept too']],
    ]);

    expect($block->fresh()?->content)->toBe(['anything' => 'kept', 'body' => ['ru' => 'kept too']]);
});

it('does not strip unknown keys of an existing row while reading it', function (): void {
    $block = Make::block(attributes: ['content' => ['body' => ['ru' => '<p>Текст</p>']]]);

    CmsBlock::query()->whereKey($block->getKey())->update([
        'content' => json_encode(['body' => ['ru' => '<p>Текст</p>'], 'legacy' => 'still here'], JSON_THROW_ON_ERROR),
    ]);

    expect($block->fresh()?->content)->toBe(['body' => ['ru' => '<p>Текст</p>'], 'legacy' => 'still here']);
});
