<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\MoonShineCms\Tests\Fixtures\OriginOptions;
use Ewk\MoonShineCms\Tests\Fixtures\SourceOptions;

/**
 * A block whose values depend on the owner it stands on: `source` keeps a
 * choice everywhere and loses `subject` where no subject exists, `origin` is
 * left with one value and therefore with no selector at all.
 */
final class SourcedBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'sourced';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('С источником')
            ->category('content')
            ->fields([
                SelectField::make('source')
                    ->label('Источник данных')
                    ->options([
                        'subject' => 'Из сущности страницы',
                        'manual' => 'Вручную',
                        'query' => 'Выборка по параметрам',
                    ])
                    ->optionsFor(SourceOptions::class)
                    ->default('manual'),
                SelectField::make('origin')
                    ->label('Откуда берётся')
                    ->options(['subject' => 'Из сущности страницы', 'manual' => 'Вручную'])
                    ->optionsFor(OriginOptions::class),
                TextField::make('title')->label('Заголовок')->translatable(),
                LinkField::make('show_all')
                    ->label('Ссылка «Показать все»')
                    ->allowedKinds([LinkKind::PAGE->value, LinkKind::LISTING->value]),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
