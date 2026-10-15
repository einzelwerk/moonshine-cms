<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Fields;

use Closure;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\UI\Fields\Field;

/**
 * A caption stored as a dictionary of languages, one input per language of
 * the {@see LanguageCatalog}, submitted as `column[locale]` — the same shape
 * the block editor uses for a translatable field.
 *
 * The value is written through `setAttribute()` and never through
 * `data_set()` into the cast attribute: the latter changes a copy of the
 * array and the model never learns about it. A model that keeps its
 * translations behind a trait is served through that trait, so the field
 * works for the package models and for a subclass of the application alike.
 *
 * A value that does not live on the edited record — metadata sits in a row of
 * `cms_metadata` of its own — is handed in with {@see self::translations()}
 * instead. The field then neither fills itself from the record nor applies
 * itself to it, and the whole tab is written by an action of its own, see {@see
 * \Ewk\MoonShineCms\Actions\SaveSeoMetadata}. One class covers both cases: a
 * translatable caption is one kind of input, wherever its dictionary is stored.
 */
final class TranslatableText extends Field
{
    protected string $view = 'moonshine-cms::fields.translatable-text';

    private bool $multiline = false;

    /**
     * Inside a form that carries one language switch of its own, see
     * {@see \Ewk\MoonShineCms\MoonShine\Components\LocaleScope}: the inputs
     * stay in the place the form declares them and only the chosen language is
     * visible. Without the switch the field shows every language at once, which
     * is what a list preview and a form without the scope need.
     */
    private bool $switcher = false;

    /**
     * Whether the input of the default language seeds the address field of the
     * form, see {@see \Ewk\MoonShineCms\MoonShine\Components\LocaleScope}.
     */
    private bool $slugSource = false;

    private ?int $recommended = null;

    /**
     * The dictionary handed in from outside, `null` while the field reads the
     * edited record itself.
     *
     * @var array<string, string>|null
     */
    private ?array $external = null;

    public function textarea(): static
    {
        $this->multiline = true;

        return $this;
    }

    public function switcher(): static
    {
        $this->switcher = true;

        return $this;
    }

    public function slugSource(): static
    {
        $this->slugSource = true;

        return $this;
    }

    /**
     * The length a search engine recommends. It is shown as a counter and never
     * enforced: the length is a recommendation, and silently cutting somebody's
     * title is not ours to do.
     */
    public function recommended(int $characters): static
    {
        $this->recommended = $characters;

        return $this;
    }

    /**
     * The stored dictionary of a value that is not a column of the edited
     * record. Handing it in detaches the field from the record in both
     * directions: it shows what it was given and writes nothing back.
     *
     * @param  array<array-key, mixed>|null  $translations
     */
    public function translations(?array $translations): static
    {
        $dictionary = [];

        foreach ($translations ?? [] as $locale => $value) {
            if (is_string($value) && $value !== '') {
                $dictionary[(string) $locale] = $value;
            }
        }

        $this->external = $dictionary;

        return $this;
    }

    /**
     * Rules for the dictionary: the default language is required when the
     * field is, the rest may stay empty and fall back.
     *
     * @return array<string, list<string>>
     */
    public function rules(int $maxLength = 255): array
    {
        $catalog = self::catalog();
        $required = (bool) $this->getAttribute('required');
        $rules = [$this->getColumn() => [$required ? 'required' : 'nullable', 'array']];

        foreach ($catalog->locales() as $locale) {
            $rules[$this->getColumn().'.'.$locale] = [
                $required && $locale === $catalog->defaultLocale() ? 'required' : 'nullable',
                'string',
                'max:'.$maxLength,
            ];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function prepareFill(array $raw = [], ?DataWrapperContract $casted = null): mixed
    {
        if ($this->external !== null) {
            return $this->external;
        }

        $model = $casted?->getOriginal();

        if ($model instanceof Model) {
            if (method_exists($model, 'getTranslations')) {
                $translations = $model->getTranslations($this->getColumn());

                return is_array($translations) ? $translations : [];
            }

            $stored = $model->getAttribute($this->getColumn());

            return is_array($stored) ? $stored : [];
        }

        $stored = $raw[$this->getColumn()] ?? null;

        return is_array($stored) ? $stored : [];
    }

    protected function resolveOnApply(): Closure
    {
        return function (mixed $item): mixed {
            if ($this->external !== null) {
                return $item;
            }

            $translations = $this->translationsFromRequest();

            if ($translations === null || ! $item instanceof Model) {
                return $item;
            }

            if (method_exists($item, 'replaceTranslations')) {
                $item->replaceTranslations($this->getColumn(), $translations);
            } else {
                $item->setAttribute($this->getColumn(), $translations);
            }

            return $item;
        };
    }

    protected function resolvePreview(): string
    {
        return e(Translated::caption($this->values()));
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
            'values' => $this->values(),
            'multiline' => $this->multiline,
            'switcher' => $this->switcher,
            'slugSource' => $this->slugSource,
            'recommended' => $this->recommended,
            'required' => (bool) $this->getAttribute('required'),
            'disabled' => (bool) $this->getAttribute('disabled'),
            'readonly' => (bool) $this->getAttribute('readonly'),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function values(): array
    {
        if ($this->external !== null) {
            return $this->external;
        }

        $value = $this->toValue();

        return is_array($value) ? $value : [];
    }

    /**
     * The submitted dictionary with the empty languages dropped, so that a
     * missing translation falls back instead of being an empty string.
     *
     * @return array<string, string>|null null when the field is absent from the request
     */
    private function translationsFromRequest(): ?array
    {
        $submitted = $this->getRequestValue();

        if ($submitted === false) {
            return null;
        }

        if (! is_array($submitted)) {
            return [];
        }

        $translations = [];

        foreach ($submitted as $locale => $translation) {
            if (! is_string($locale) || ! is_string($translation) || trim($translation) === '') {
                continue;
            }

            $translations[$locale] = $translation;
        }

        return $translations;
    }

    private static function catalog(): LanguageCatalog
    {
        return Container::getInstance()->make(LanguageCatalog::class);
    }
}
