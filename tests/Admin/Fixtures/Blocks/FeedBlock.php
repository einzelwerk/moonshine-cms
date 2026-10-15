<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Params\BooleanParam;
use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A dynamic block whose editor decides both the size of a portion and the mode.
 * Both settings live in `content` without being fields, which is exactly the
 * case the card has to draw.
 */
final class FeedBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'feed';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Лента')
            ->category('content')
            ->fields([
                TextField::make('title')->label('Заголовок')->translatable(),
            ])
            ->params([
                StringParam::make('city')->label('Город')->nullable()->filter(),
                // A filter whose values are a declared set: the form of a
                // catalogue link offers them instead of asking for a code.
                EnumParam::make('category')
                    ->label('Категория')
                    ->options(['music' => 'Музыка', 'theatre' => 'Театр'])
                    ->nullable()
                    ->filter(),
                DateParam::make('opens_on')->label('Дата')->nullable()->filter(),
                BooleanParam::make('free')->label('Бесплатные')->nullable()->filter(),
            ])
            ->pagination(
                PaginationMode::PAGES,
                perPage: 5,
                maxPerPage: 20,
                editable: true,
                modeEditable: true,
                allowedModes: [PaginationMode::PAGES, PaginationMode::MORE],
            )
            ->placement(root: '*', parents: '*', shared: false);
    }
}
