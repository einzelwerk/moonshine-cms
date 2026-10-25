<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Resources;

use Ewk\Cms\Blocks\Contracts\CollectsOwnerAnchors;
use Ewk\Cms\Blocks\OpenApi\DescribesPayloadSchema;
use Illuminate\Http\Request;
use OpenApi\Annotations\Schema as OpenApiSchema;
use OpenApi\Attributes as OA;

/**
 * The table of contents adds to its own heading the anchors of the whole owner.
 * The items are handed out as data; no markup is built here.
 */
final class TocBlockResource extends SchemaBlockResource implements CollectsOwnerAnchors, DescribesPayloadSchema
{
    /**
     * The items are the anchors of the whole page and not a stored value, so
     * the generator cannot derive the payload from the declaration: only the
     * heading is a field.
     */
    public static function payloadSchema(): OpenApiSchema
    {
        return new OA\Schema(
            description: 'Оглавление: якоря всей страницы, а не своего слота',
            required: ['title', 'items'],
            properties: [
                new OA\Property(property: 'title', description: 'Заголовок; пустой — подпись по умолчанию', type: 'string'),
                new OA\Property(
                    property: 'items',
                    description: 'Пункты оглавления',
                    type: 'array',
                    items: new OA\Items(
                        required: ['anchor', 'title'],
                        properties: [
                            new OA\Property(property: 'anchor', description: 'Якорь блока', type: 'string'),
                            new OA\Property(property: 'title', description: 'Заголовок', type: 'string'),
                        ],
                        type: 'object',
                    ),
                ),
            ],
            type: 'object',
        );
    }

    /** @var list<array{anchor: string, title: string}> */
    private array $anchors = [];

    public function withCollectedAnchors(array $anchors): static
    {
        $this->anchors = $anchors;

        return $this;
    }

    protected function toData(Request $request): array
    {
        return [...parent::toData($request), 'items' => $this->anchors];
    }
}
