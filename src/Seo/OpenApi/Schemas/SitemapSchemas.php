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
    description: 'Один файл карты сайта.',
    required: ['id'],
    properties: [
        new OA\Property(
            property: 'id',
            description: 'Имя файла: `<язык>-<источник>-<номер>`, на одноязычном сайте `<источник>-<номер>`. '
                .'Источники: `pages` — страницы CMS, дальше по одному на шаблонный тип страниц (`tour`, `event`, …).',
            type: 'string',
            example: 'ru-event-1',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CmsSitemapEntry',
    description: 'Одна запись карты сайта в форме `MetadataRoute.Sitemap` Next.',
    required: ['url'],
    properties: [
        new OA\Property(property: 'url', description: 'Абсолютный адрес с языком', type: 'string', format: 'uri', example: 'https://tixcom.eu/ru/tours/nutcracker'),
        new OA\Property(
            property: 'lastModified',
            description: 'Когда изменилось то, что видит посетитель: публикация страницы, правка записи или её метаданных. '
                .'Ключа нет, когда честной даты нет.',
            type: 'string',
            format: 'date-time',
            example: '2026-10-01T12:00:00+00:00',
        ),
        new OA\Property(
            property: 'alternates',
            description: 'Только на многоязычном сайте с префиксом языка в адресах.',
            required: ['languages'],
            properties: [
                new OA\Property(
                    property: 'languages',
                    description: 'Код языка → адрес версии, включая саму запись, плюс `x-default` на язык по умолчанию.',
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
