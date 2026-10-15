<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Exceptions;

use Ewk\Cms\Blocks\Fields\Field;
use RuntimeException;

/**
 * A field type of the core dictionary that this UI has no component for. The
 * adapter refuses instead of rendering a silent text input: a value edited
 * through the wrong control is worse than a visible gap.
 */
final class UnsupportedFieldTypeException extends RuntimeException
{
    public static function forField(Field $field): self
    {
        return new self(
            'No MoonShine component is declared for the field type ['.$field::class.'].',
        );
    }
}
