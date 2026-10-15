<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Media\Localization\LanguageCatalog;

final readonly class StaticLanguageCatalog implements LanguageCatalog
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        private array $locales,
        private string $defaultLocale,
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
