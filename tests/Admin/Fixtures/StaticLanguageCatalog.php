<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Media\Localization\LanguageCatalog;

/**
 * A catalog that does not depend on configuration: the editor must read the
 * languages from the contract, never from a hardcoded list.
 */
final readonly class StaticLanguageCatalog implements LanguageCatalog
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        private array $locales = ['de', 'fr'],
        private string $default = 'de',
    ) {
    }

    public function locales(): array
    {
        return $this->locales;
    }

    public function defaultLocale(): string
    {
        return $this->default;
    }
}
