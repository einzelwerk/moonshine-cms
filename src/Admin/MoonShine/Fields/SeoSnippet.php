<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Fields;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use MoonShine\UI\Fields\Field;

/**
 * The title and the description of one record with the three things the form of
 * hand-filled metadata owes an editor: a preview of the search snippet, a
 * character counter on both values and the hint of what a fallback would put
 * into an empty field.
 *
 * The two values live in one field for one reason: a snippet is drawn from
 * both of them at once, and two fields would need two Alpine scopes plus a
 * bus between them to keep one preview in step.
 *
 * Submitted as `seo[title][<locale>]` and `seo[description][<locale>]` — the
 * same shape {@see TranslatableText} uses, so {@see \Ewk\MoonShineCms\Actions\SaveSeoMetadata}
 * reads the whole tab one way.
 */
final class SeoSnippet extends Field
{
    public const int TITLE_LENGTH = 60;

    public const int DESCRIPTION_LENGTH = 160;

    protected string $view = 'moonshine-cms::fields.seo-snippet';

    /** @var array<string, string> */
    private array $titles = [];

    /** @var array<string, string> */
    private array $descriptions = [];

    private ?string $fallbackTitle = null;

    private string $previewUrl = '';

    /**
     * @param  array<array-key, mixed>|null  $titles
     * @param  array<array-key, mixed>|null  $descriptions
     */
    public function values(?array $titles, ?array $descriptions): static
    {
        $this->titles = self::dictionary($titles);
        $this->descriptions = self::dictionary($descriptions);

        return $this;
    }

    /**
     * What the document builder would put into an empty title: the name of the
     * record. Shown greyed out in the preview, so an empty field reads as
     * "filled in by the fallback" rather than as broken.
     */
    public function fallbackTitle(?string $title): static
    {
        $this->fallbackTitle = $title === null || trim($title) === '' ? null : $title;

        return $this;
    }

    public function previewUrl(string $url): static
    {
        $this->previewUrl = $url;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            // Strings and never the field itself: the view data is also the
            // JSON of the field, and a field inside its own data is a cycle
            // the reactive answer of the form cannot encode.
            'nameAttribute' => $this->getNameAttribute(),
            'nameDot' => $this->getNameDot(),
            'locales' => self::catalog()->locales(),
            'defaultLocale' => self::catalog()->defaultLocale(),
            'titles' => $this->titles,
            'descriptions' => $this->descriptions,
            'fallbackTitle' => $this->fallbackTitle,
            'previewUrl' => $this->previewUrl,
            'titleLength' => self::TITLE_LENGTH,
            'descriptionLength' => self::DESCRIPTION_LENGTH,
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $stored
     * @return array<string, string>
     */
    private static function dictionary(?array $stored): array
    {
        $dictionary = [];

        foreach ($stored ?? [] as $locale => $value) {
            if (is_string($value) && $value !== '') {
                $dictionary[(string) $locale] = $value;
            }
        }

        return $dictionary;
    }

    private static function catalog(): LanguageCatalog
    {
        return Container::getInstance()->make(LanguageCatalog::class);
    }
}
