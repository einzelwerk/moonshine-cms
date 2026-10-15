<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Exceptions\DuplicatePageTypeException;
use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;
use Ewk\Cms\Pages\Exceptions\UnknownPageTypeException;
use Ewk\Cms\Pages\Tests\Fixtures\Concert;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;

it('registers, finds and lists declarations', function (): void {
    $registry = new PageTypeRegistry();
    TestPageTypes::registerAll($registry);

    expect($registry->has('concert'))->toBeTrue()
        ->and($registry->has('venue'))->toBeFalse()
        ->and($registry->get('home')->cardinality())->toBe(Cardinality::SINGLE)
        ->and(array_keys($registry->all()))->toBe(['page', 'home', 'concert']);
});

it('throws for an unknown key instead of guessing', function (): void {
    expect(fn () => (new PageTypeRegistry())->get('venue'))->toThrow(UnknownPageTypeException::class);
});

it('refuses a second declaration of the same key', function (): void {
    $registry = new PageTypeRegistry();
    $registry->register(TestPageTypes::page());

    expect(fn () => $registry->register(TestPageTypes::page()))->toThrow(DuplicatePageTypeException::class);
});

it('refuses a subject without a visibility criterion', function (): void {
    $type = PageType::make('concert')
        ->cardinality(Cardinality::TEMPLATE)
        ->subject(Subject::model(Concert::class)->by('slug'))
        ->path('/concerts/{concert}');

    expect(fn () => (new PageTypeRegistry())->register($type))
        ->toThrow(InvalidPageTypeException::class, 'visibility criterion');
});

it('refuses a template that resolves nothing', function (PageType $type, string $message): void {
    expect(fn () => (new PageTypeRegistry())->register($type))->toThrow(InvalidPageTypeException::class, $message);
})->with([
    'no subject' => [
        fn () => PageType::make('concert')->cardinality(Cardinality::TEMPLATE)->path('/concerts/{concert}'),
        'declares no subject',
    ],
    'no path' => [
        fn () => PageType::make('concert')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Concert::class)->visible('publiclyVisible')),
        'declares no path template',
    ],
    'no placeholder for the subject' => [
        fn () => PageType::make('concert')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Concert::class)->visible('publiclyVisible'))
            ->path('/concerts/{slug}'),
        'placeholder to read the subject from',
    ],
]);

it('refuses an address where the cardinality has none to declare', function (): void {
    $many = PageType::make('page')->path('/pages');
    $single = PageType::make('home')->cardinality(Cardinality::SINGLE);
    $parametrized = PageType::make('home')->cardinality(Cardinality::SINGLE)->path('/{anything}');

    expect(fn () => (new PageTypeRegistry())->register($many))
        ->toThrow(InvalidPageTypeException::class, 'comes from the tree')
        ->and(fn () => (new PageTypeRegistry())->register($single))
        ->toThrow(InvalidPageTypeException::class, 'declares no path template')
        ->and(fn () => (new PageTypeRegistry())->register($parametrized))
        ->toThrow(InvalidPageTypeException::class, 'carries placeholders');
});

it('refuses a subject on a type that has no address to read it from', function (): void {
    $type = PageType::make('page')->subject(Subject::model(Concert::class)->visible('publiclyVisible'));

    expect(fn () => (new PageTypeRegistry())->register($type))
        ->toThrow(InvalidPageTypeException::class, 'cannot declare a subject');
});

it('refuses a key that is not an identifier', function (): void {
    expect(fn () => PageType::make('Page Type'))->toThrow(InvalidPageTypeException::class);
});
