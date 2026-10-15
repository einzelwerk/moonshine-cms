<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Seo;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\MoonShine\Fields\MediaAttachmentsField;
use Ewk\Cms\Media\MoonShine\Fields\MediaPicker;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;
use Ewk\Cms\Seo\Enums\OpenGraphType;
use Ewk\Cms\Seo\Enums\TwitterCard;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Support\SiteUrl;
use Ewk\MoonShineCms\MoonShine\Fields\SeoSnippet;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\Rules\CanonicalAddressRule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Collections\Fields;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The form of the hand-filled metadata. Everything is typed by an editor: there
 * is no generation, and the only automatic thing in the package is the fallback
 * computed when the document is handed out, so the form's job is to say what
 * that fallback would be.
 *
 * The values are read here, once for the whole tab, and handed to the fields:
 * metadata lives in a row of its own, and a field filling itself from the
 * edited record would find nothing there. For the same reason every field is
 * detached from the apply of the panel and the whole tab is written by
 * {@see \Ewk\MoonShineCms\Actions\SaveSeoMetadata}, the way the chrome form
 * does it, see {@see \Ewk\MoonShineCms\Actions\SaveChrome}.
 */
final readonly class SeoFields
{
    /**
     * The one prefix every input of the tab lives under, so that a column of
     * the edited record can never be shadowed by a field of this form:
     * `description` exists on both a tour and its metadata.
     */
    public const string PREFIX = 'seo';

    public function __construct(
        private SiteUrl $siteUrl,
        private LanguageCatalog $catalog,
    ) {
    }

    /**
     * @return list<ComponentContract>
     */
    public function components(?Model $owner, ?string $fallbackTitle = null): array
    {
        $metadata = $owner === null ? null : CmsMetadata::of($owner);

        $components = [
            Box::make((string) __('moonshine-cms::seo.sections.snippet'), [
                // No caption of its own: the box above already says what this
                // is, and a second heading between them read as a field label
                // belonging to the title input.
                SeoSnippet::make('', self::PREFIX)
                    ->values($metadata?->title, $metadata?->description)
                    ->fallbackTitle($fallbackTitle)
                    ->previewUrl($this->previewUrl($owner)),
            ]),
            // The social cards and the indexing sit folded: they are filled on
            // a handful of pages, and open they pushed the snippet, which is
            // what an editor came for, off the screen.
            Collapse::make((string) __('moonshine-cms::seo.sections.social'), [
                self::text('og_title', $metadata?->og_title),
                self::text('og_description', $metadata?->og_description, multiline: true),
                self::select('og_type', OpenGraphType::options(), $metadata?->og_type),
                ...$this->media('og_image', $metadata?->og_image),
            ]),
            Collapse::make((string) __('moonshine-cms::seo.sections.twitter'), [
                self::text('tw_title', $metadata?->tw_title),
                self::text('tw_description', $metadata?->tw_description, multiline: true),
                self::select('tw_card', TwitterCard::options(), $metadata?->tw_card),
                ...$this->media('tw_image', $metadata?->tw_image),
            ]),
            Collapse::make((string) __('moonshine-cms::seo.sections.indexing'), [
                Switcher::make((string) __('cms-seo::seo.fields.robots_index'), self::column('robots_index'))
                    ->default($metadata === null || $metadata->robots_index),
                Switcher::make((string) __('cms-seo::seo.fields.robots_follow'), self::column('robots_follow'))
                    ->default($metadata === null || $metadata->robots_follow),
                Text::make((string) __('cms-seo::seo.fields.canonical'), self::column('canonical'))
                    ->hint((string) __('cms-seo::seo.fallbacks.canonical'))
                    ->onBeforeRender(static function (Text $field) use ($metadata): Text {
                        $field->setValue($metadata?->canonical);

                        return $field;
                    }),
            ]),
        ];

        return self::detached($components);
    }

    /**
     * Rules for the tab. The length of a title is a recommendation and is
     * therefore not validated; the column limits are.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $rules = [
            self::PREFIX => ['nullable', 'array'],
        ];

        foreach (CmsMetadata::TRANSLATABLE as $attribute) {
            $rules[self::column($attribute)] = ['nullable', 'array'];
            $rules[self::column($attribute).'.*'] = ['nullable', 'string', 'max:1000'];
        }

        $rules[self::column('og_type')] = ['nullable', 'string', 'in:'.implode(',', OpenGraphType::values())];
        $rules[self::column('tw_card')] = ['nullable', 'string', 'in:'.implode(',', TwitterCard::values())];
        $rules[self::column('robots_index')] = ['nullable', 'boolean'];
        $rules[self::column('robots_follow')] = ['nullable', 'boolean'];
        $rules[self::column('canonical')] = ['nullable', 'string', 'max:2048', new CanonicalAddressRule()];

        foreach (CmsMetadata::IMAGE_COLUMNS as $image) {
            $rules[self::column($image)] = ['nullable', 'array'];
            $rules[self::column($image).'.*.id'] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * The address the snippet shows. A page of the tree knows its own path; an
     * entity is drawn by a template, and the template declares its address as a
     * pattern, so the path of the entity is built from that declaration and
     * never written out here.
     *
     * An owner no declared type stands for falls back to the root of the
     * site: a wrong address under the preview would be worse than a bare
     * host.
     */
    private function previewUrl(?Model $owner): string
    {
        $locale = app()->getLocale();

        if ($owner instanceof CmsPage) {
            return $this->siteUrl->absolute($owner->path !== '' ? $owner->path : '/', $locale);
        }

        return $this->siteUrl->absolute($owner === null ? '/' : $this->subjectPath($owner), $locale);
    }

    /**
     * The path of an entity behind a template address, `/tours/nutcracker` and
     * `/tours/nutcracker/2026-06-01` alike: the placeholder named after the
     * type carries the slug of the entity, the remaining ones are read back
     * through the accessors the declaration names, see {@see Subject::context()}.
     */
    private function subjectPath(Model $owner): string
    {
        $type = $this->typeOf($owner);
        $pattern = $type?->pattern();
        $subject = $type?->subject();

        if ($type === null || $pattern === null || ! $subject instanceof Subject) {
            return '/';
        }

        $values = [$type->key() => self::stringOf($owner->getAttribute($subject->slugColumn))];

        foreach ($subject->context as $placeholder => $accessor) {
            $values[$placeholder] = self::stringOf(data_get($owner, $accessor));
        }

        foreach ($values as $value) {
            // A half built address shows its braces rather than collapsing
            // into a shorter, wrong one; the preview simply shows the root
            // instead.
            if ($value === '') {
                return '/';
            }
        }

        // A value that is no segment of an address has no address at all, and
        // the preview says the root the same way it does for a half built one.
        return $pattern->build($values) ?? '/';
    }

    private function typeOf(Model $owner): ?PageType
    {
        return Container::getInstance()->make(PageTypeRegistry::class)->forSubject($owner);
    }

    private static function stringOf(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<array-key, mixed>|null  $stored
     */
    private static function text(string $attribute, ?array $stored, bool $multiline = false): TranslatableText
    {
        // The same recommended lengths the snippet counts by: a card of a
        // social network trims a title and a description no later than the
        // search results do.
        $field = TranslatableText::make((string) __('cms-seo::seo.fields.'.$attribute), self::column($attribute))
            ->translations($stored)
            ->switcher()
            ->recommended($multiline ? SeoSnippet::DESCRIPTION_LENGTH : SeoSnippet::TITLE_LENGTH)
            ->hint((string) __('cms-seo::seo.fallbacks.'.$attribute));

        return $multiline ? $field->textarea() : $field;
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function select(string $attribute, array $options, ?string $stored): Select
    {
        return Select::make((string) __('cms-seo::seo.fields.'.$attribute), self::column($attribute))
            ->options($options)
            ->nullable()
            // The empty entry of a select reads as a word: a dash is a mark of
            // a table and not a choice.
            ->customAttributes(['placeholder' => (string) __('moonshine-cms::fields.not_chosen')])
            ->native()
            ->hint((string) __('cms-seo::seo.fallbacks.'.$attribute))
            ->onBeforeRender(static function (Select $field) use ($stored): Select {
                $field->setValue($stored);

                return $field;
            });
    }

    /**
     * The picture is a reference to the media library and never a path — the
     * same picker the block editor uses, so the upload creates a record instead
     * of dropping a file on the panel disk.
     *
     * One picker per language, like the inputs of a translatable text: a
     * language version is shared on its own and the words on a picture are
     * words of one language. Each picker is marked with its language, so the
     * one switch of the form shows the chosen one and the rest wait hidden.
     *
     * @param  array<array-key, mixed>|null  $stored  language => key of the record
     * @return list<MediaPicker>
     */
    private function media(string $attribute, ?array $stored): array
    {
        $default = $this->catalog->defaultLocale();
        $pickers = [];

        foreach ($this->catalog->locales() as $locale) {
            $id = $stored[$locale] ?? null;
            $uuid = self::uuidOf(is_int($id) ? $id : null);

            $wrapper = [
                'data-cms-locale' => $locale,
                // The condition names no language of its own, see
                // {@see MediaAttachmentsField::locale()}: the element reads the
                // language off the mark it already carries.
                'x-show' => 'typeof locale === typeof undefined ? true : locale === $el.dataset.cmsLocale',
            ];

            if ($locale !== $default) {
                $wrapper['style'] = 'display:none';
            }

            $pickers[] = MediaPicker::make((string) __('cms-seo::seo.fields.'.$attribute), self::column($attribute).'.'.$locale)
                // The row of the metadata holds the key of the picture and
                // nothing about it: the column has no companion for a caption,
                // and {@see \Ewk\MoonShineCms\Actions\SaveSeoMetadata} reads the
                // reference alone. A description offered here would be typed
                // and dropped without a word.
                ->withoutDescription()
                ->hint((string) __('cms-seo::seo.fallbacks.'.$attribute))
                ->customWrapperAttributes($wrapper)
                ->onBeforeRender(static function (MediaPicker $field) use ($uuid): MediaPicker {
                    $field->setValue($uuid === null ? [] : ['id' => $uuid]);

                    return $field;
                });
        }

        return $pickers;
    }

    /**
     * The column stores the key of the record and the picker speaks uuids. The
     * column carries no foreign key into the media library, so a reference to a
     * record that is gone reads as no picture.
     */
    private static function uuidOf(?int $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        $media = CmsMedia::query()->find($mediaId);

        return $media instanceof CmsMedia ? $media->uuid : null;
    }

    private static function column(string $attribute): string
    {
        return self::PREFIX.'.'.$attribute;
    }

    /**
     * Metadata is not a column of the edited record, so no field of this tab
     * takes part in the apply of the panel: `data_set()` would write into a
     * record that does not own the value, and an uploaded file would land on
     * the panel disk instead of in the library.
     *
     * @param  list<ComponentContract>  $components
     * @return list<ComponentContract>
     */
    private static function detached(array $components): array
    {
        Fields::make($components)
            ->onlyFields(withWrappers: true, withApplyWrappers: true)
            ->each(static function (ComponentContract $field): void {
                if ($field instanceof FieldContract) {
                    $field->canApply(static fn (): bool => false);
                }
            });

        return $components;
    }
}
