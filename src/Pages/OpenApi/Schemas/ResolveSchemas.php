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
 * `subject.data` points at `CmsSubjectData`, which an installation declares as
 * the union of its own public resources; without such a declaration the
 * processor of the blocks adds an open object under that name.
 *
 * The keys an application writes with a contributor of its own live in
 * `CmsResolveExtension`, which the answer takes into its `allOf`: the same
 * arrangement, an open placeholder unless the installation declares the name.
 */
#[OA\Schema(
    schema: 'CmsResolveResponse',
    description: 'Разрешённый адрес: страница, её субъект, блоки и языковые версии.',
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
                    required: ['uuid', 'name', 'layout_code', 'published_at'],
                    properties: [
                        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', description: 'Название страницы на языке запроса', type: 'string'),
                        new OA\Property(property: 'layout_code', description: 'Код оболочки из GET /v1/chrome', type: 'string', nullable: true),
                        new OA\Property(property: 'published_at', description: 'Дата публикации снимка, ISO-8601', type: 'string', format: 'date-time', nullable: true),
                    ],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'subject',
                    description: 'Сущность шаблонного адреса; `null` на обычной странице. `data` отдаёт презентер, '
                        .'объявленный типом страницы: тип страницы знает, чем он адресуется, ядро — нет.',
                    required: ['type', 'slug', 'data'],
                    properties: [
                        new OA\Property(property: 'type', description: 'Ключ типа страницы, которому принадлежит субъект', type: 'string'),
                        new OA\Property(property: 'slug', type: 'string'),
                        // An extension point: the application declares
                        // `CmsSubjectData` as the union of its public
                        // resources, otherwise the processor of the blocks
                        // puts an open object there.
                        new OA\Property(property: 'data', ref: '#/components/schemas/CmsSubjectData'),
                    ],
                    type: 'object',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'blocks',
                    description: 'Блоки снимка в порядке редактора; дети контейнеров лежат в `children`',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CmsBlock'),
                ),
                new OA\Property(
                    property: 'languages',
                    description: 'Коды языков каталога',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                ),
                new OA\Property(property: 'metadata', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/CmsMetadata')]),
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
    schema: 'CmsBlockResponse',
    description: 'Ответ endpoint-а одного блока: тот же конверт, что приезжает в `blocks` резолвера, '
        .'поэтому нового типа на фронте не появляется.',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/CmsBlock'),
    ],
    type: 'object',
)]
final class ResolveSchemas
{
}
