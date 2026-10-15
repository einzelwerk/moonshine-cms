<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Media;

use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\RepeaterField;

/**
 * Every media reference a bag of content holds, found by walking the declared
 * fields and not by searching the json for anything that looks like a uuid: the
 * schema is what says where a file may sit, and a text that happens to contain
 * a uuid is not a usage.
 *
 * The answer is keyed by the dotted path the value sits at — `image`,
 * `slides.0.photo`, `contacts.logo`, `image.ru` — because that path is what
 * `cms_media_usages` stores and what an admin shows when it lists where a file
 * is used.
 *
 * Whether a value is a dictionary of languages is read off the schema and never
 * off the shape of the value: a translatable group or repeater looks exactly
 * like a plain one from the outside, and guessing would walk right past the
 * files inside it.
 */
final class MediaReferenceCollector
{
    /**
     * @param  array<array-key, mixed>  $content
     * @param  list<Field>  $fields
     * @return array<string, string>  dotted path => media uuid
     */
    public function collect(array $content, array $fields): array
    {
        $found = [];

        $this->walk($fields, $content, '', $found);

        return $found;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @param  array<string, string>  $found
     */
    private function walk(array $fields, array $values, string $prefix, array &$found): void
    {
        foreach ($fields as $field) {
            $name = $field->name();

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $value = $values[$name];
            $path = $prefix === '' ? $name : $prefix.'.'.$name;

            if (! $field->isTranslatable()) {
                $this->value($field, $value, $path, $found);

                continue;
            }

            // A translatable value is a dictionary of languages, so the
            // language is a segment of the path and the value under it is read
            // by the very same rules as an untranslated one.
            foreach (is_array($value) ? $value : [] as $locale => $localized) {
                $this->value($field, $localized, $path.'.'.$locale, $found);
            }
        }
    }

    /**
     * @param  array<string, string>  $found
     */
    private function value(Field $field, mixed $value, string $path, array &$found): void
    {
        if ($field instanceof MediaField) {
            self::reference($value, $path, $found);

            return;
        }

        if ($field instanceof GroupField && is_array($value)) {
            $this->walk($field->getFields(), $value, $path, $found);

            return;
        }

        if ($field instanceof RepeaterField && is_array($value)) {
            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $this->walk($field->getFields(), $item, $path.'.'.$index, $found);
                }
            }
        }
    }

    /**
     * A reference is `{"id": "<uuid>"}`.
     *
     * @param  array<string, string>  $found
     */
    private static function reference(mixed $value, string $path, array &$found): void
    {
        if (! is_array($value)) {
            return;
        }

        $uuid = self::uuid($value['id'] ?? null);

        if ($uuid !== null) {
            $found[$path] = $uuid;
        }
    }

    private static function uuid(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $uuid = trim($raw);

        return $uuid === '' ? null : $uuid;
    }
}
