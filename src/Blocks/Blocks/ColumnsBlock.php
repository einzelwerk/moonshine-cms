<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Http\Resources\ColumnsBlockResource;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\Slot;

/**
 * The first container of the package. The layout is described by a twelve
 * column grid: every slot carries a span, the spans of a variant add up to
 * twelve, and the variant decides how many slots there are.
 *
 * What the core does not hand out: paddings, alignment, backgrounds, radii and
 * any classes. It describes structure and proportions, the look belongs to the
 * frontend, so nothing here depends on Tailwind.
 */
final class ColumnsBlock extends AbstractBlock implements ContainerBlockContract
{
    public const DEFAULT_VARIANT = '2';

    /**
     * Spans of every shipped variant on the twelve column grid: `2` is six and
     * six, `3` is three fours, `4` is four threes, `1-2` is four and eight.
     * Every row adds up to twelve.
     */
    private const SPANS = [
        '2' => [6, 6],
        '3' => [4, 4, 4],
        '4' => [3, 3, 3, 3],
        '1-2' => [4, 8],
    ];

    public static function code(): string
    {
        return 'columns';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title((string) __('cms-blocks::blocks.columns.title'))
            ->category('layout')
            ->variants([
                '2' => (string) __('cms-blocks::blocks.columns.variants.2'),
                '3' => (string) __('cms-blocks::blocks.columns.variants.3'),
                '4' => (string) __('cms-blocks::blocks.columns.variants.4'),
                '1-2' => (string) __('cms-blocks::blocks.columns.variants.1-2'),
            ])
            ->fields([
                BooleanField::make('collapse_on_mobile')
                    ->label((string) __('cms-blocks::blocks.columns.fields.collapse_on_mobile'))
                    ->default(true),
            ])
            ->placement(root: '*', parents: '*', shared: true);
    }

    /**
     * @return class-string<BlockResource>
     */
    public function resource(): string
    {
        return ColumnsBlockResource::class;
    }

    public function slots(?string $variant): array
    {
        return self::slotsFor($variant);
    }

    /**
     * An unknown variant falls back to the two column layout instead of
     * losing the children of the row: the variant of a stored block is
     * validated by the schema, and a stale value must still render.
     *
     * @return list<Slot>
     */
    public static function slotsFor(?string $variant): array
    {
        $spans = self::SPANS[$variant ?? self::DEFAULT_VARIANT] ?? self::SPANS[self::DEFAULT_VARIANT];

        $slots = [];

        foreach ($spans as $index => $span) {
            $position = $index + 1;

            $slots[] = new Slot(
                name: 'col_'.$position,
                title: (string) __('cms-blocks::blocks.columns.slot', ['position' => $position]),
                allow: '*',
                min: 1,
                max: null,
                span: $span,
            );
        }

        return $slots;
    }
}
