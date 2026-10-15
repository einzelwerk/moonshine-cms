<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Schema;

use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Media\Localization\LanguageCatalog;

/**
 * Derives the Laravel rules of a block's `content` from its schema.
 *
 * A translatable field yields a rule for the dictionary and a rule per
 * language of the catalog. A partial translation is allowed: `required()` on
 * the field means "must have a value in the default language", every other
 * language stays optional.
 *
 * The captions of the same keys come from here as well, see
 * {@see self::attributes()}: the two lists are one walk over the schema, so a
 * rule never appears without the caption a refusal names it by.
 */
final readonly class ValidationRuleBuilder
{
    public function __construct(private LanguageCatalog $catalog)
    {
    }

    /**
     * The context narrows the values a field accepts to the ones its owner
     * offers. Without one every declared value passes, which is what a caller
     * outside a form gets.
     *
     * @return array<string, list<mixed>>
     */
    public function build(BlockSchema $schema, ?FieldContext $context = null): array
    {
        return $this->rulesFor($schema->fields(), 'content', $context ?? FieldContext::none());
    }

    /**
     * What a validator calls the fields of the schema, keyed like the rules of
     * {@see self::build()}, wildcards of a repeater included. Without it a
     * refused save says «content.items.0.label.ru» instead of the caption an
     * editor typed into: Laravel resolves the caption of a concrete path
     * through the rule it was validated by, so one entry covers every row.
     *
     * A language is named only where there is more than one: on a single
     * language installation «Подпись (RU)» would tell an editor nothing. A
     * field without a label falls back to its name.
     *
     * @return array<string, string>
     */
    public function attributes(BlockSchema $schema): array
    {
        return $this->captionsFor($schema->fields(), 'content');
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, string>
     */
    private function captionsFor(array $fields, string $prefix): array
    {
        $captions = [];
        $locales = $this->catalog->locales();

        foreach ($fields as $field) {
            $key = $prefix.'.'.$field->name();
            $label = $field->getLabel() ?? $field->name();
            $captions[$key] = $label;

            if ($field->isTranslatable()) {
                foreach ($locales as $locale) {
                    $captions[$key.'.'.$locale] = count($locales) > 1
                        ? $label.' ('.mb_strtoupper($locale).')'
                        : $label;
                }
            }

            if ($field instanceof GroupField) {
                $captions += $this->captionsFor($field->getFields(), $key);
            } elseif ($field instanceof RepeaterField) {
                $captions += $this->captionsFor($field->getFields(), $key.'.*');
            }
        }

        return $captions;
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, list<mixed>>
     */
    private function rulesFor(array $fields, string $prefix, FieldContext $context): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = $prefix.'.'.$field->name();

            if ($field->isTranslatable()) {
                $rules[$key] = $this->presence($field->isRequired(), ['array']);

                foreach ($this->catalog->locales() as $locale) {
                    $required = $field->isRequired() && $locale === $this->catalog->defaultLocale();
                    $rules[$key.'.'.$locale] = $this->presence($required, $field->rulesIn($context));
                }
            } else {
                $rules[$key] = $this->presence($field->isRequired(), $field->rulesIn($context));
            }

            $rules += $this->nestedRules($field, $key, $context);
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function nestedRules(Field $field, string $key, FieldContext $context): array
    {
        if ($field instanceof GroupField) {
            return $this->rulesFor($field->getFields(), $key, $context);
        }

        if ($field instanceof RepeaterField) {
            return $this->rulesFor($field->getFields(), $key.'.*', $context);
        }

        return [];
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    private function presence(bool $required, array $rules): array
    {
        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes', 'nullable'], $rules);
    }
}
