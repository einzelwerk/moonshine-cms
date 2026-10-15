<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Ewk\Cms\Pages\Models\CmsPage;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The page model of the installation.
 *
 * The package model is not final: an application extends it to mix in what
 * belongs to it and not to the CMS, see {@see CmsPage}. Every query the package
 * runs against pages goes through here, so the rows it hands back are instances
 * of the class the application declared in `cms-pages.model` and carry its
 * traits, not bare package instances.
 */
final class PageModel
{
    /**
     * @return class-string<CmsPage>
     */
    public static function class(): string
    {
        $container = Container::getInstance();
        $configured = $container->bound('config') ? $container->make('config')->get('cms-pages.model') : null;

        if ($configured === null) {
            return CmsPage::class;
        }

        if (! is_string($configured) || ! is_a($configured, CmsPage::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'cms-pages.model must name a subclass of %s, [%s] given.',
                CmsPage::class,
                is_string($configured) ? $configured : get_debug_type($configured),
            ));
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): CmsPage
    {
        $class = self::class();

        return new $class($attributes);
    }

    /**
     * @return Builder<CmsPage>
     */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
