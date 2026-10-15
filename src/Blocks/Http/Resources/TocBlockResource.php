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
            description: 'Оглавление: заголовок блока и якоря всей страницы, а не своего слота. '
                .'Пустой заголовок означает подпись по умолчанию на стороне фронта.',
            required: ['title', 'items'],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        required: ['anchor', 'title'],
                        properties: [
                            new OA\Property(property: 'anchor', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
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
