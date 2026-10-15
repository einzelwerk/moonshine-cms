<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Ewk\Cms\Media\Localization\LanguageCatalog;

/**
 * The content languages of the installation, the same contract the blocks
 * package publishes; an application binds its own implementation over the
 * `languages` table.
 */
final readonly class StaticLanguageCatalog implements LanguageCatalog
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        private array $locales = ['ru', 'en', 'de-CH'],
        private string $defaultLocale = 'ru',
    ) {
    }

    public function locales(): array
    {
        return $this->locales;
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
