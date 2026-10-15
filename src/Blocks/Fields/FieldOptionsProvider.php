<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

/**
 * Values a {@see SelectField} offers when the set depends on the owner of the
 * block: the sources of a listing block, which lose `subject` wherever no
 * subject exists.
 *
 * Declared by class and resolved through the container at the moment it is
 * asked, never held by the declaration itself: a schema stays a plain
 * description, the same way {@see \Ewk\Cms\Blocks\Params\OptionsProvider} keeps
 * the parameters of a block plain.
 */
interface FieldOptionsProvider
{
    /**
     * @return array<string, string> value => label, in the order the form shows them
     */
    public function options(FieldContext $context): array;
}
