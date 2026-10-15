<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The flat list of the library with a search over the name and the code:
 * folders are out of the first version. The code is searched by but not shown:
 * it is edited on the form, and a column of codes says nothing about which
 * block is which.
 *
 * @extends IndexPage<SharedBlockResource>
 */
class SharedBlockIndexPage extends IndexPage
{
    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Text::make(__('moonshine-cms::fields.name'), 'name')->sortable(),
            // Counted by the query of the list, see
            // SharedBlockResource::modifyQueryBuilder(): a count per row turns
            // the list into one query per record.
            //
            // A bare number in a column headed «where it is inserted» reads as
            // an identifier, so the cell answers the question in words.
            Text::make(
                __('moonshine-cms::shared.usages'),
                'insertions_count',
                static fn (CmsSharedBlock $sharedBlock): string => self::usages($sharedBlock),
            ),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->updateOnPreview()->sortable(),
        ];
    }

    private static function usages(CmsSharedBlock $sharedBlock): string
    {
        $count = SharedBlockResource::insertionCount($sharedBlock);

        return (string) ($count === 0
            ? __('moonshine-cms::shared.usages_none')
            : __('moonshine-cms::shared.usages_count', ['count' => $count]));
    }
}
