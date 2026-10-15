<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Http\Resources\TocBlockResource;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * Table of contents: it collects the anchors of the whole owner and has one
 * value of its own, the caption above the list. It is not shareable — a table
 * of contents in the shared library would describe a page it knows nothing
 * about.
 */
final class TocBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'toc';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title((string) __('cms-blocks::blocks.toc.title'))
            ->category('content')
            ->fields([
                // Optional on purpose: an empty heading means the frontend
                // keeps its own default caption, so an editor who has nothing
                // to say leaves the field alone.
                TextField::make('title')
                    ->label((string) __('cms-blocks::blocks.toc.fields.title'))
                    ->help((string) __('cms-blocks::blocks.toc.help.title'))
                    ->translatable(),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }

    /**
     * @return class-string<BlockResource>
     */
    public function resource(): string
    {
        return TocBlockResource::class;
    }
}
