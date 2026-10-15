<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\FieldOptionsProvider;

/**
 * The sources of a listing block: the entity of the page exists only where the
 * page has one. Two values are left everywhere, so the selector stays a
 * selector.
 */
final class SourceOptions implements FieldOptionsProvider
{
    public function options(FieldContext $context): array
    {
        $options = [];

        if ($context->hasSubject()) {
            $options['subject'] = 'Из сущности страницы';
        }

        $options['manual'] = 'Вручную';
        $options['query'] = 'Выборка по параметрам';

        return $options;
    }
}
