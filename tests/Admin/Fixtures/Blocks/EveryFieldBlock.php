<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Fields\ColorField;
use Ewk\Cms\Blocks\Fields\CoordinatesField;
use Ewk\Cms\Blocks\Fields\DateField;
use Ewk\Cms\Blocks\Fields\DateTimeField;
use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\MultiSelectField;
use Ewk\Cms\Blocks\Fields\NumberField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;

/**
 * A block that declares one field of every type of the core dictionary, so the
 * adapter is exercised as a whole and not type by type.
 */
final class EveryFieldBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'every_field';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Все поля')
            ->category('content')
            ->variants(['wide' => 'Широкий', 'narrow' => 'Узкий'])
            ->fields([
                TextField::make('title')->label('Заголовок')->translatable()->required()->maxLength(120),
                RichTextField::make('body')->label('Текст')->translatable(),
                NumberField::make('weight')->label('Вес')->min(1)->max(9)->integer(),
                NumberField::make('ratio')->label('Доля')->integer(false),
                BooleanField::make('manual')->label('Вручную')->default(false),
                TextField::make('venue')->label('Площадка')->visibleWhen('manual', true),
                DateField::make('starts_on')->label('Дата'),
                DateTimeField::make('starts_at')->label('Дата и время'),
                SelectField::make('align')
                    ->label('Выравнивание')
                    ->options(['left' => 'Слева', 'right' => 'Справа'])
                    ->help('Куда прижать содержимое'),
                MultiSelectField::make('tags')->label('Метки')->options(['a' => 'A', 'b' => 'B']),
                MediaField::make('image')->label('Картинка'),
                // Every kind at once: the picker of the link is exercised whole.
                LinkField::make('cta')->label('Кнопка'),
                EntityField::make('concert')->label('Концерт')->targets([Catalogue::class]),
                ColorField::make('accent')->label('Акцент')->group('Оформление'),
                CoordinatesField::make('point')->label('Точка')->group('Оформление'),
                GroupField::make('seo')->label('SEO')->fields([
                    TextField::make('slug')->label('Слаг'),
                ]),
                RepeaterField::make('items')->label('Пункты')->max(3)->fields([
                    TextField::make('label')->label('Подпись'),
                    NumberField::make('order')->label('Порядок')->integer(),
                ]),
            ])
            ->placement(root: '*', parents: '*', shared: true);
    }
}
