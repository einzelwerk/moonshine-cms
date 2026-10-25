<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * The two answers of the resolver.
 *
 * Written by hand and not generated, unlike the blocks: the shape of the
 * document is fixed by the pages and does not depend on what an application
 * registers. `CmsBlock`, `CmsMetadata` and `CmsJsonLd` are the generated and
 * the neighbouring components it points at.
 *
 * `metadata` and `json_ld` are put in by the contributor of the SEO part and
 * are therefore optional here: without a contributor the answer goes without
 * the keys rather than with empty ones.
 *
 * The keys an application writes with a contributor of its own live in
 * `CmsResolveExtension`, which the answer takes into its `allOf`: the same
 * arrangement, an open placeholder unless the installation declares the name.
 */
#[OA\Schema(
    schema: 'CmsResolveResponse',
    description: 'Разрешённый адрес: страница, сущность, блоки и языки',
    type: 'object',
    // An extension point: the keys the contributors of the application write
    // are described in `CmsResolveExtension`. The application declares that
    // component itself, otherwise the processor of the blocks puts an open
    // object there. The body of the answer is the second member of `allOf`
    // together with its `required`: otherwise swagger-php moves only the
    // `properties` into `allOf` and leaves `required` outside, and the
    // generator of the frontend makes every key optional.
    allOf: [
        new OA\Schema(ref: '#/components/schemas/CmsResolveExtension'),
        new OA\Schema(
            required: ['type', 'path', 'page', 'subject', 'blocks', 'languages'],
            properties: [
                new OA\Property(property: 'type', description: 'Ключ типа страницы', type: 'string', nullable: true, example: 'tour'),
                new OA\Property(property: 'path', description: 'Нормализованный путь без префикса языка', type: 'string', example: '/tours/stand-up-action'),
                new OA\Property(
                    property: 'page',
                    description: 'Страница',
                    required: ['uuid', 'name', 'layout_code', 'published_at'],
                    properties: [
                        new OA\Property(property: 'uuid', description: 'UUID', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', description: 'Название на языке ответа', type: 'string'),
                        new OA\Property(property: 'layout_code', description: 'Код лейаута из GET /v1/chrome', type: 'string', nullable: true),
                        new OA\Property(property: 'published_at', description: 'Дата и время публикации снимка', type: 'string', format: 'date-time', nullable: true),
                    ],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'subject',
                    description: 'Сущность шаблонного адреса; null на обычной странице',
                    type: 'object',
                    nullable: true,
                    allOf: [new OA\Schema(ref: '#/components/schemas/CmsResolveSubject')],
                ),
                new OA\Property(
                    property: 'blocks',
                    description: 'Блоки снимка в порядке редактора; дети контейнеров — в `children`',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CmsBlock'),
                ),
                new OA\Property(
                    property: 'languages',
                    description: 'Коды языков каталога',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                ),
                new OA\Property(property: 'metadata', description: 'Метаданные страницы', type: 'object', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/CmsMetadata')]),
                new OA\Property(
                    property: 'json_ld',
                    description: 'Объекты schema.org страницы',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CmsJsonLd'),
                ),
            ],
            type: 'object',
        ),
    ],
)]
#[OA\Schema(
    schema: 'CmsResolveSubject',
    description: 'Сущность шаблонного адреса со slug',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/BlockSubject'),
        new OA\Schema(
            required: ['slug'],
            properties: [
                new OA\Property(property: 'slug', description: 'Slug для URL', type: 'string'),
            ],
            type: 'object',
        ),
    ],
)]
#[OA\Schema(
    schema: 'CmsBlockResponse',
    description: 'Блок в том же конверте, что в `blocks` резолвера',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', description: 'Блок', allOf: [new OA\Schema(ref: '#/components/schemas/CmsBlock')]),
    ],
    type: 'object',
)]
final class ResolveSchemas
{
}
