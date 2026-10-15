<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\OpenApi;

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Illuminate\Container\Container;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAT;
use OpenApi\Undefined;

/**
 * The swagger-php processor that writes the dictionary of menu slots into the
 * contract as a property per slot.
 *
 * Prose would not do it. Every slot is a place in the markup of the frontend,
 * so the dictionary is closed on purpose, and a map with free-form keys is
 * exactly the shape that obliges the frontend to draw a place it does not
 * know. Written out as properties, `chrome.footers[].slots.footer_1` is a
 * field of a generated type and a typo in it stops compiling.
 *
 * A processor and not an attribute, for the same reason the block schemas of
 * the blocks are generated: the set is a runtime fact. The shipped slots are an
 * enum, but an installation adds its own through `cms-layouts.menu_slots`, and
 * no doc comment in this package can know them.
 *
 * The container is asked for the catalog at the moment of the call and never
 * in the constructor: the processor is named in `config/l5-swagger.php`, which
 * is read long before the application is booted.
 */
final class MenuSlotsProcessor
{
    /** The schema whose `slots` property carries the dictionary. */
    public const string SCHEMA = 'ChromePart';

    public const string PROPERTY = 'slots';

    public function __invoke(Analysis $analysis): void
    {
        $slots = $this->slots();

        if ($slots === null) {
            return;
        }

        foreach ($this->properties($analysis) as $property) {
            $properties = [];

            // The places of the chrome only: a place declared for a record of
            // the installation is never a key of this map, see
            // MenuSlotCatalog::chromeCodes().
            foreach ($slots->chromeCodes() as $code) {
                $properties[] = new OAT\Property(
                    property: $code,
                    description: 'Коды меню в слоте, в порядке сортировки',
                    type: 'array',
                    items: new OAT\Items(type: 'string'),
                );
            }

            $property->properties = $properties;

            // The closed dictionary, said in the one way a generated client
            // can read: a key outside it is not a slot of this installation.
            $property->additionalProperties = false;
        }
    }

    /**
     * Every `slots` property of the document. A list and not a single hit:
     * the schema is one, but nothing in the contract forbids a second
     * declaration, and silently patching the first would be worse than
     * patching both.
     *
     * @return list<OA\Property>
     */
    private function properties(Analysis $analysis): array
    {
        $openapi = $analysis->openapi;

        if (! $openapi instanceof OA\OpenApi || Undefined::isDefault($openapi->components)) {
            return [];
        }

        $components = $openapi->components;

        if (Undefined::isDefault($components->schemas)) {
            return [];
        }

        $found = [];

        foreach ($components->schemas as $schema) {
            if (Undefined::isDefault($schema->schema) || (string) $schema->schema !== self::SCHEMA) {
                continue;
            }

            if (Undefined::isDefault($schema->properties)) {
                continue;
            }

            foreach ($schema->properties as $property) {
                if (! Undefined::isDefault($property->property) && (string) $property->property === self::PROPERTY) {
                    $found[] = $property;
                }
            }
        }

        return $found;
    }

    private function slots(): ?MenuSlotCatalog
    {
        $container = Container::getInstance();

        if (! $container->bound(MenuSlotCatalog::class)) {
            return null;
        }

        /** @var MenuSlotCatalog $catalog */
        $catalog = $container->make(MenuSlotCatalog::class);

        return $catalog;
    }
}
