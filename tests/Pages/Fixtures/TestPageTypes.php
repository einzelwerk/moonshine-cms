<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;

/**
 * The three cardinalities of a page type, declared over the fixture catalog:
 * this package ships no types of its own.
 */
final class TestPageTypes
{
    public static function page(): PageType
    {
        return PageType::make('page')
            ->title('Страница')
            ->cardinality(Cardinality::MANY);
    }

    public static function home(): PageType
    {
        return PageType::make('home')
            ->title('Главная')
            ->cardinality(Cardinality::SINGLE)
            ->path('/');
    }

    public static function concert(): PageType
    {
        return PageType::make('concert')
            ->title('Концерт')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(
                Subject::model(Concert::class)
                    ->by('slug')
                    ->visible('publiclyVisible')
                    ->context(['series' => 'series.slug']),
            )
            ->path('/series/{series}/{concert}')
            ->required(['hero', 'checkout'])
            ->limits(['hero' => 1])
            ->starter(['hero', 'checkout']);
    }

    /**
     * A template type that declares a presenter of its subject, and demands
     * no blocks, so publishing its template needs nothing else.
     */
    public static function gig(): PageType
    {
        return PageType::make('gig')
            ->title('Концерт')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(
                Subject::model(Concert::class)
                    ->by('slug')
                    ->visible('publiclyVisible')
                    ->presenter(ConcertPresenter::class),
            )
            ->path('/gigs/{gig}');
    }

    /**
     * A template type that declares no presenter: the answer of the resolver
     * still carries a subject, with an empty `data`.
     */
    public static function recital(): PageType
    {
        return PageType::make('recital')
            ->title('Рецитал')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Concert::class)->by('slug')->visible('publiclyVisible'))
            ->path('/recitals/{recital}');
    }

    /**
     * A template whose starter set names only registered types.
     */
    public static function article(): PageType
    {
        return PageType::make('article')
            ->title('Статья')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Concert::class)->by('slug')->visible('publiclyVisible'))
            ->path('/articles/{article}')
            ->starter(['text', 'text']);
    }

    public static function registerAll(PageTypeRegistry $registry): void
    {
        $registry->register(self::page());
        $registry->register(self::home());
        $registry->register(self::concert());
    }
}
