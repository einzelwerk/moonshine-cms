<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\FieldOptionsProvider;

/**
 * A provider that leaves the editor no choice whatever the owner is: one
 * value, so the field is settled and not shown.
 */
final class OnlyManualOptions implements FieldOptionsProvider
{
    public function options(FieldContext $context): array
    {
        return ['manual' => 'Вручную'];
    }
}
