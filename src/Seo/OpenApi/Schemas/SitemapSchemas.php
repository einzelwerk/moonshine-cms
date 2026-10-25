<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * The two shapes of the sitemap feed. An entry is shaped after
 * `MetadataRoute.Sitemap` of Next, so the frontend returns the list from its
 * `sitemap()` without rework.
 */
#[OA\Schema(
    schema: 'CmsSitemapFile',
    description: 'Файл карты сайта',
    required: ['id'],
    properties: [
        new OA\Property(
            property: 'id',
            description: 'Имя файла `<язык>-<источник>-<номер>`, на одноязычном сайте `<источник>-<номер>`; '
                .'источник — `pages` или ключ шаблонного типа страниц',
            type: 'string',
            example: 'ru-event-1',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CmsSitemapEntry',
    description: 'Запись карты сайта в форме `MetadataRoute.Sitemap` Next',
    required: ['url'],
    properties: [
        new OA\Property(property: 'url', description: 'Абсолютный адрес с языком', type: 'string', format: 'uri', example: 'https://tixcom.eu/ru/tours/nutcracker'),
        new OA\Property(
            property: 'lastModified',
            description: 'Дата и время последнего видимого изменения; ключа нет, если дата неизвестна',
            type: 'string',
            format: 'date-time',
            example: '2026-10-01T12:00:00+00:00',
        ),
        new OA\Property(
            property: 'alternates',
            description: 'Языковые версии; только на многоязычном сайте с префиксом языка',
            required: ['languages'],
            properties: [
                new OA\Property(
                    property: 'languages',
                    description: 'Код языка → адрес версии, включая саму запись, плюс `x-default`',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string', format: 'uri'),
                ),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
final class SitemapSchemas
{
}
