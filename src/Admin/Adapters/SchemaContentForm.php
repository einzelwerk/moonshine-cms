<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Adapters;

use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\MoonShineCms\Actions\SaveSchemaContent;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Collections\Fields;

/**
 * The fields of a declared schema on the form of a record that is not built
 * out of blocks: the header and the footer of the site, or a record of an
 * installation. Drawn by the very adapter the block editor uses, so no second
 * way of turning a field declaration into a form appears.
 *
 * The fields are detached from the generic apply of the panel: their value is
 * written as a whole by {@see SaveSchemaContent}, and a half-write by
 * `data_set()` into a cast attribute would only produce a value nobody stored.
 */
final readonly class SchemaContentForm
{
    public function __construct(private FieldAdapter $adapter)
    {
    }

    /**
     * With the switch of the form: a translatable value is drawn in the place
     * the schema declares it and shows the chosen language, instead of sinking
     * into a tab strip of languages at the end. The form wraps them in a {@see
     * \Ewk\MoonShineCms\MoonShine\Components\LocaleScope}.
     *
     * @return list<ComponentContract>
     */
    public function fields(BlockSchema $schema): array
    {
        $components = $this->adapter->withLocaleSwitcher()->form($schema);

        Fields::make($components)
            ->onlyFields(withWrappers: true, withApplyWrappers: true)
            ->each(static function (ComponentContract $field): void {
                if ($field instanceof FieldContract) {
                    $field->canApply(static fn (): bool => false);
                }
            });

        return $components;
    }
}
