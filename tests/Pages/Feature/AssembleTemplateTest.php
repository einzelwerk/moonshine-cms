<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Exceptions\UnknownPageTypeException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\AssembleTemplate;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

beforeEach(function (): void {
    $registry = app(PageTypeRegistry::class);
    TestPageTypes::registerAll($registry);
    // A template whose starter set is fully registered.
    $registry->register(TestPageTypes::article());
});

function assembler(): AssembleTemplate
{
    return app(AssembleTemplate::class);
}

it('creates one inactive row with the starter blocks of the declaration', function (): void {
    $page = assembler()('article');

    expect($page->exists)->toBeTrue()
        ->and($page->is_active)->toBeFalse()
        ->and($page->path)->toBe('/articles/{article}')
        ->and($page->name)->toBe(['ru' => 'Статья'])
        ->and($page->blocks()->orderBy('sort_order')->pluck('type')->all())->toBe(['text', 'text']);
});

it('skips a starter code the installation has not registered', function (): void {
    $page = assembler()('concert');

    expect($page->blocks()->count())->toBe(0)
        ->and(CmsPage::query()->where('type', 'concert')->count())->toBe(1);
});

it('reports the existing template of a type, a draft before an active one', function (): void {
    expect(assembler()->existing('article'))->toBeNull();

    $draft = assembler()('article');

    expect(assembler()->existing('article')?->is($draft))->toBeTrue();
});

it('refuses a type that is not a template and an unknown type', function (): void {
    expect(fn () => assembler()('page'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => assembler()('nonsense'))->toThrow(UnknownPageTypeException::class)
        ->and(CmsPage::query()->count())->toBe(0);
});
