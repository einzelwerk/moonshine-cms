<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

final class TextBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'text';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title((string) __('cms-blocks::blocks.text.title'))
            ->category('content')
            ->variants([
                'default' => (string) __('cms-blocks::blocks.text.variants.default'),
                'narrow' => (string) __('cms-blocks::blocks.text.variants.narrow'),
            ])
            ->fields([
                TextField::make('title')
                    ->label((string) __('cms-blocks::blocks.text.fields.title'))
                    ->translatable(),
                RichTextField::make('body')
                    ->label((string) __('cms-blocks::blocks.text.fields.body'))
                    ->translatable()
                    ->required(),
            ])
            ->placement(root: '*', parents: '*', shared: true);
    }
}
