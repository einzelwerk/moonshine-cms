<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Media;

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;

/**
 * How a record of the CMS is named in the list of «where this file is used».
 *
 * The default language and not the one the reader asked for: the list is an
 * index of the content and not the content, and a page called «Контакты» has
 * to be recognisable there whichever language the panel happens to speak.
 *
 * It lives in this package because every package that names an owner already
 * depends on it, and the media package deliberately does not: the library
 * knows nothing about translatable names.
 */
final class UsageOwnerCaption
{
    private function __construct()
    {
    }

    public static function of(mixed $name, string $fallback = ''): string
    {
        $locale = self::defaultLocale();
        $stored = is_array($name) || is_string($name) ? $name : null;
        $caption = trim(LocalizedValueResolver::resolve($stored, $locale, $locale));

        return $caption === '' ? $fallback : $caption;
    }

    private static function defaultLocale(): string
    {
        $container = Container::getInstance();

        if ($container->bound(LanguageCatalog::class)) {
            return $container->make(LanguageCatalog::class)->defaultLocale();
        }

        $configured = $container->bound('config') ? $container->make('config')->get('app.locale') : null;

        return is_string($configured) && $configured !== '' ? $configured : 'en';
    }
}
