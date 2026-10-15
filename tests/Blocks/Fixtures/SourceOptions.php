<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\FieldOptionsProvider;

/**
 * The source selector of a listing block as an application declares it: the
 * entity of the page exists only where the page has one, everything else is
 * offered everywhere.
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

        return $options;
    }
}
