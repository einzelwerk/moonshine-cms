<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * The values of a block that the owner leaves no choice about: a select with
 * exactly one option on offer is not a choice, so the form draws no selector
 * and the value is settled here.
 *
 * Two callers need the same answer. The editor fills the hidden input of the
 * card with it, and the save writes it into the content whatever the request
 * carried, so a card saved without that input keeps the only value it could
 * ever have had.
 *
 * Only the fields of the block itself are settled: a value nested in a group
 * or a row of a repeater describes a part of the content and not where the
 * block stands.
 */
final class FixedFieldValues
{
    /**
     * @return array<string, string> field name => the only value it offers
     */
    public static function of(BlockSchema $schema, FieldContext $context): array
    {
        $fixed = [];

        foreach ($schema->fields() as $field) {
            if (! $field instanceof SelectField) {
                continue;
            }

            $options = $field->optionsIn($context);

            if (count($options) !== 1) {
                continue;
            }

            $fixed[$field->name()] = (string) array_key_first($options);
        }

        return $fixed;
    }
}
