<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;

/**
 * The three cardinalities of a page type, the way an installation declares
 * them: a free page, a singleton at the root and a template rendered for a
 * catalogue entry.
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
            ->title('Шаблон концерта')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Catalogue::class)->by('name')->visible('publiclyVisible'))
            ->path('/concerts/{concert}')
            ->required(['schedule'])
            ->limits(['schedule' => 1])
            ->starter(['schedule', 'text', 'unregistered']);
    }

    public static function exhibition(): PageType
    {
        return PageType::make('exhibition')
            ->title('Шаблон выставки')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Catalogue::class)->by('name')->visible('publiclyVisible'))
            ->path('/exhibitions/{exhibition}')
            ->starter(['text']);
    }

    public static function registerAll(PageTypeRegistry $registry): void
    {
        $registry->register(self::page());
        $registry->register(self::home());
        $registry->register(self::concert());
        $registry->register(self::exhibition());
    }
}
