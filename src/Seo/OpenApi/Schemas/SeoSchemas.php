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
    description: 'Метаданные страницы в форме Next Metadata: ключи camelCase, адреса абсолютные с языком',
    required: ['title', 'description', 'alternates', 'openGraph', 'twitter', 'robots'],
    properties: [
        new OA\Property(property: 'title', description: 'Заголовок; пустой — название страницы или сущности', type: 'string', nullable: true),
        new OA\Property(property: 'description', description: 'Описание', type: 'string', nullable: true),
        new OA\Property(
            property: 'alternates',
            description: 'Канонический адрес и языковые версии',
            required: ['canonical', 'languages'],
            properties: [
                new OA\Property(property: 'canonical', description: 'Канонический адрес: параметры пагинации остаются, фильтры исключены', type: 'string'),
                new OA\Property(
                    property: 'languages',
                    description: 'Код языка → адрес версии, плюс `x-default`; пуст у неопубликованной страницы',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                ),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'openGraph',
            description: 'Open Graph',
            required: ['title', 'description', 'type', 'images'],
            properties: [
                new OA\Property(property: 'title', description: 'Заголовок', type: 'string', nullable: true),
                new OA\Property(property: 'description', description: 'Описание', type: 'string', nullable: true),
                new OA\Property(property: 'type', description: 'Тип объекта Open Graph', type: 'string', example: 'website'),
                new OA\Property(
                    property: 'images',
                    description: 'Изображения',
                    type: 'array',
                    items: new OA\Items(
                        required: ['url'],
                        properties: [
                            new OA\Property(property: 'url', description: 'Абсолютный адрес', type: 'string', format: 'uri'),
                            new OA\Property(property: 'width', description: 'Ширина в пикселях; только когда известна', type: 'integer'),
                            new OA\Property(property: 'height', description: 'Высота в пикселях; только когда известна', type: 'integer'),
                        ],
                        type: 'object',
                    ),
                ),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'twitter',
            description: 'Карточка Twitter',
            required: ['card', 'title', 'description', 'images'],
            properties: [
                new OA\Property(property: 'card', description: 'Тип карточки', type: 'string', example: 'summary_large_image'),
                new OA\Property(property: 'title', description: 'Заголовок', type: 'string', nullable: true),
                new OA\Property(property: 'description', description: 'Описание', type: 'string', nullable: true),
                new OA\Property(property: 'images', description: 'Адреса изображений', type: 'array', items: new OA\Items(type: 'string', format: 'uri')),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'robots',
            description: 'Указания поисковым роботам',
            required: ['index', 'follow'],
            properties: [
                new OA\Property(property: 'index', description: 'Индексировать страницу', type: 'boolean'),
                new OA\Property(property: 'follow', description: 'Переходить по ссылкам', type: 'boolean'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CmsJsonLd',
    description: 'Объект schema.org; набор ключей зависит от `@type`, фронт выводит его как есть',
    required: ['@context', '@type'],
    properties: [
        new OA\Property(property: '@context', description: 'Контекст schema.org', type: 'string', example: 'https://schema.org'),
        new OA\Property(property: '@type', description: 'Тип schema.org', type: 'string', example: 'Event'),
    ],
    type: 'object',
    additionalProperties: true,
)]
final class SeoSchemas
{
}
