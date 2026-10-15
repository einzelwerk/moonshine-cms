<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\BlockRegistry;
use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Blocks\TocBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\DuplicateBlockCodeException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockClassException;
use Ewk\Cms\Blocks\Exceptions\UnknownBlockCodeException;
use Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource;
use Ewk\Cms\Blocks\Http\Resources\TocBlockResource;
use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Blocks\Tests\Fixtures\DuplicateTextBlock;

it('registers the block types listed in configuration', function (): void {
    $registry = app(BlockRegistryInterface::class);

    expect($registry->all())->toBe([
        'text' => TextBlock::class,
        'toc' => TocBlock::class,
        'columns' => ColumnsBlock::class,
        'shared' => SharedInsertionBlock::class,
    ])
        ->and($registry->has('text'))->toBeTrue()
        ->and($registry->has('gallery'))->toBeFalse()
        ->and($registry->classFor('toc'))->toBe(TocBlock::class)
        ->and($registry->make('text')->resource())->toBe(SchemaBlockResource::class)
        ->and($registry->make('toc')->resource())->toBe(TocBlockResource::class)
        ->and($registry->schemaFor('text')->title())->toBe('Text');
});

it('reads the captions of the shipped blocks out of the translations of the package', function (): void {
    $registry = new BlockRegistry(app());
    $registry->register(TextBlock::class);
    $registry->register(ColumnsBlock::class);

    app()->setLocale('ru');

    expect($registry->schemaFor('text')->title())->toBe('Текст')
        ->and($registry->schemaFor('text')->variants())->toBe([
            'default' => 'Обычный',
            'narrow' => 'Узкая колонка',
        ])
        ->and($registry->schemaFor('columns')->fields()[0]->getLabel())
        ->toBe('Схлопывать в одну колонку на узком экране')
        ->and(ColumnsBlock::slotsFor('2')[0]->title)->toBe('Колонка 1');

    app()->setLocale('en');

    expect($registry->schemaFor('text')->title())->toBe('Text')
        ->and(ColumnsBlock::slotsFor('2')[0]->title)->toBe('Column 1');
});

it('refuses a second class for a taken code', function (): void {
    $registry = new BlockRegistry(app());
    $registry->register(TextBlock::class);

    expect(fn () => $registry->register(DuplicateTextBlock::class))
        ->toThrow(DuplicateBlockCodeException::class);
});

it('accepts registering the very same class twice', function (): void {
    $registry = new BlockRegistry(app());
    $registry->register(TextBlock::class);
    $registry->register(TextBlock::class);

    expect($registry->all())->toBe(['text' => TextBlock::class]);
});

it('refuses a class that is not a block', function (): void {
    $registry = new BlockRegistry(app());

    expect(fn () => $registry->register(LocalizedValueResolver::class))
        ->toThrow(InvalidBlockClassException::class);
});

it('throws for an unknown code instead of guessing', function (): void {
    $registry = new BlockRegistry(app());

    expect(fn () => $registry->classFor('gallery'))->toThrow(UnknownBlockCodeException::class)
        ->and(fn () => $registry->make('gallery'))->toThrow(UnknownBlockCodeException::class)
        ->and(fn () => $registry->schemaFor('gallery'))->toThrow(UnknownBlockCodeException::class);
});
