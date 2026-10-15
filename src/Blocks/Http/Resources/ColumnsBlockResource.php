<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Resources;

use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\OpenApi\DescribesPayloadSchema;
use Ewk\Cms\Blocks\Schema\Slot;
use Illuminate\Http\Request;
use OpenApi\Annotations\Schema as OpenApiSchema;
use OpenApi\Attributes as OA;

/**
 * The payload of a container adds the proportions of its slots to the values of
 * its fields: a span from one to twelve per slot, in the order the slots are
 * declared, plus the collapse flag.
 *
 * The children themselves never live in `data`: they arrive under `children`
 * as a map by slot, so one traversal fits every container.
 */
final class ColumnsBlockResource extends SchemaBlockResource implements DescribesPayloadSchema
{
    /**
     * The shares of the slots come from the variant and not from a stored
     * value, so they are not in the declaration the generator reads.
     */
    public static function payloadSchema(): OpenApiSchema
    {
        return new OA\Schema(
            description: 'Контейнер колонок: доли слотов на двенадцатиколоночной сетке, в порядке '
                .'объявления. Сами дети приезжают в `children`.',
            required: ['widths', 'collapse_on_mobile'],
            properties: [
                new OA\Property(
                    property: 'widths',
                    type: 'array',
                    items: new OA\Items(type: 'integer', maximum: 12, minimum: 1),
                ),
                new OA\Property(property: 'collapse_on_mobile', type: 'boolean'),
            ],
            type: 'object',
        );
    }

    protected function toData(Request $request): array
    {
        $widths = array_map(
            static fn (Slot $slot): int => $slot->span,
            ColumnsBlock::slotsFor($this->block->variant),
        );

        return ['widths' => $widths, ...parent::toData($request)];
    }
}
