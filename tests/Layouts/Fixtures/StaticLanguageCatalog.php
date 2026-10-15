<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Tests\Fixtures;

use Ewk\Cms\Media\Localization\LanguageCatalog;

/**
 * The content languages of the installation, the contract the blocks package
 * publishes. Binding a fixture keeps these tests about the chrome instead of
 * about somebody else's configuration.
 */
final readonly class StaticLanguageCatalog implements LanguageCatalog
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        private array $locales = ['ru', 'en'],
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
