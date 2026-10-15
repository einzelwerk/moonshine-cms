<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * What the SEO contributor adds to the answer of the resolver.
 *
 * The object is shaped after the Metadata of Next: the frontend hands it to
 * the framework as it arrives and composes nothing itself. Addresses are
 * absolute and carry the language, because that is what a tag, a letter and a
 * sitemap need; the relative `href` of a link is a different thing.
 */
#[OA\Schema(
    schema: 'CmsMetadata',
    description: 'Метаданные страницы в форме Next Metadata: ключи camelCase, адреса абсолютные и с языком.',
    required: ['title', 'description', 'alternates', 'openGraph', 'twitter', 'robots'],
    properties: [
        new OA\Property(property: 'title', description: 'Заголовок; при пустом поле — название страницы или сущности', type: 'string', nullable: true),
        new OA\Property(property: 'description', description: 'Описание; фолбэка у него нет — пустой тег хуже отсутствующего', type: 'string', nullable: true),
        new OA\Property(
            property: 'alternates',
            required: ['canonical', 'languages'],
            properties: [
                new OA\Property(property: 'canonical', description: 'Канонический адрес: позиционные параметры пагинации остаются, фильтрующие исключены', type: 'string'),
                new OA\Property(
                    property: 'languages',
                    description: 'Код языка → адрес версии, плюс `x-default`. У неопубликованной страницы словарь пуст.',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                ),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'openGraph',
            required: ['title', 'description', 'type', 'images'],
            properties: [
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'type', type: 'string', example: 'website'),
                new OA\Property(
                    property: 'images',
                    type: 'array',
                    items: new OA\Items(
                        required: ['url'],
                        properties: [
                            new OA\Property(property: 'url', type: 'string', format: 'uri'),
                            new OA\Property(property: 'width', description: 'Только когда размер файла по этому адресу известен', type: 'integer'),
                            new OA\Property(property: 'height', type: 'integer'),
                        ],
                        type: 'object',
                    ),
                ),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'twitter',
            required: ['card', 'title', 'description', 'images'],
            properties: [
                new OA\Property(property: 'card', type: 'string', example: 'summary_large_image'),
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string', format: 'uri')),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'robots',
            required: ['index', 'follow'],
            properties: [
                new OA\Property(property: 'index', type: 'boolean'),
                new OA\Property(property: 'follow', type: 'boolean'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CmsJsonLd',
    description: 'Объект schema.org страницы. Форма зависит от `@type` и построителя приложения, '
        .'поэтому набор ключей открыт: разметку строит бэкенд, фронт печатает её как есть.',
    required: ['@context', '@type'],
    properties: [
        new OA\Property(property: '@context', type: 'string', example: 'https://schema.org'),
        new OA\Property(property: '@type', type: 'string', example: 'Event'),
    ],
    type: 'object',
    additionalProperties: true,
)]
final class SeoSchemas
{
}
