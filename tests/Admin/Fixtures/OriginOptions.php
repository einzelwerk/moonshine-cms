<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\FieldOptionsProvider;

/**
 * One value wherever it is asked, and a different one depending on the owner:
 * the case where the editor is left no choice and the card shows no selector.
 */
final class OriginOptions implements FieldOptionsProvider
{
    public function options(FieldContext $context): array
    {
        return $context->hasSubject()
            ? ['subject' => 'Из сущности страницы']
            : ['manual' => 'Вручную'];
    }
}
