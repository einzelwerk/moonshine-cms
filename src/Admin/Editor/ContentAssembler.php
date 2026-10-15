<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\MoonShine\Fields\EntityPicker;

/**
 * The inverse of {@see \Ewk\MoonShineCms\Adapters\FieldAdapter}: what a
 * MoonShine form submits becomes the content shape the core declares.
 *
 * One encoding exists only in the UI and is undone here: an entity picker
 * sends one option value for a pair of a type and a key. Everything else is
 * handed to the field's own `normalize()`, so the storage shape has exactly
 * one owner.
 *
 * A media field submits a reference and never a file. The upload happens the
 * moment an editor chooses a picture, through the endpoint of the panel, so
 * there is one path of upload and one set of rules on it.
 */
final readonly class ContentAssembler
{
    public function __construct(private LanguageCatalog $catalog)
    {
    }

    /**
     * @param  array<array-key, mixed>  $raw  the `content` part of the request
     * @return array<string, mixed>
     */
    public function assemble(BlockSchema $schema, array $raw): array
    {
        return $this->fields($schema->fields(), $raw);
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $raw
     * @return array<string, mixed>
     */
    private function fields(array $fields, array $raw): array
    {
        $content = [];

        foreach ($fields as $field) {
            $name = $field->name();

            if (! array_key_exists($name, $raw)) {
                continue;
            }

            $content[$name] = $this->value($field, $raw[$name]);
        }

        return $content;
    }

    private function value(Field $field, mixed $raw): mixed
    {
        if ($field->isTranslatable()) {
            return $field->normalize($this->onlyKnownLocales($raw));
        }

        if ($field instanceof MediaField) {
            return $field->normalize($this->media($raw));
        }

        if ($field instanceof EntityField) {
            return $field->normalize($this->entity($field, $raw));
        }

        if ($field instanceof GroupField) {
            return is_array($raw)
                ? $this->fields($field->getFields(), $raw)
                : $field->normalize($raw);
        }

        if ($field instanceof RepeaterField) {
            return $this->repeater($field, $raw);
        }

        return $field->normalize($raw);
    }

    /**
     * A language the catalog does not declare has no place in the content: the
     * form only ever shows the declared ones, so anything else is junk.
     */
    private function onlyKnownLocales(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return $raw;
        }

        $locales = $this->catalog->locales();
        $known = [];

        foreach ($raw as $locale => $value) {
            if (in_array((string) $locale, $locales, true)) {
                $known[(string) $locale] = $value;
            }
        }

        return $known;
    }

    /**
     * A reference plus the description this one place gives the picture. No
     * file ever arrives here: a picture is uploaded the moment it is chosen and
     * the form carries its uuid.
     *
     * Only `alt` is kept out of the submitted metadata, and only as strings
     * per language of the catalog. Everything else in `metadata` belongs to
     * the record of the file and is not the form's to write: a request that
     * carries more is not refused, it is simply not believed.
     *
     * @return array<string, mixed>|null
     */
    private function media(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $id = $raw['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $value = ['id' => $id];
        $alt = $this->alt($raw['metadata'] ?? null);

        if ($alt !== []) {
            $value['metadata'] = ['alt' => $alt];
        }

        return $value;
    }

    /**
     * The override of the description, language by language. An empty one is
     * dropped and not stored: empty means «inherit the description of the
     * file», and an empty string stored here would inherit nothing.
     *
     * @return array<string, string>
     */
    private function alt(mixed $metadata): array
    {
        $raw = is_array($metadata) ? ($metadata['alt'] ?? null) : null;

        if (! is_array($raw)) {
            return [];
        }

        $alt = [];

        foreach ($this->catalog->locales() as $locale) {
            $value = $raw[$locale] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $alt[$locale] = $value;
            }
        }

        return $alt;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function entity(EntityField $field, mixed $raw): ?array
    {
        if ($field->isMultiple()) {
            $values = is_array($raw) ? $raw : [$raw];
            $references = [];

            foreach ($values as $value) {
                $reference = EntityPicker::unpack($value);

                if ($reference !== null) {
                    $references[] = $reference;
                }
            }

            return $references;
        }

        return EntityPicker::unpack($raw);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function repeater(RepeaterField $field, mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $items = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                $items[] = $this->fields($field->getFields(), $item);
            }
        }

        return $items;
    }
}
