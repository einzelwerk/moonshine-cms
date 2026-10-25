<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Http\Controllers;

use Ewk\Cms\Blocks\Http\ErrorCode;
use Ewk\Cms\Seo\Sitemap\SitemapFeed;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/sitemap/{id}`: the entries of one file in the shape of
 * `MetadataRoute.Sitemap` of Next, returned by the `sitemap()` of the frontend
 * as they arrive.
 *
 * A name the feed does not have answers 404: a file announced yesterday may
 * be gone today, once the source it was cut from shrank.
 */
#[OA\Get(
    path: '/v1/sitemap/{id}',
    operationId: 'CmsSitemapFileV1',
    description: 'Записи одного файла карты сайта в форме `MetadataRoute.Sitemap` Next: адреса абсолютные '
        .'и с языком, у каждой записи — все её языковые версии, включая её саму, и `x-default`. '
        .'`priority` и `changefreq` не отдаются: поисковики их игнорируют.',
    summary: 'Файл карты сайта',
    tags: ['CMS'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            description: 'Имя файла из `GET /v1/sitemap`',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', example: 'ru-event-1'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Записи файла',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/CmsSitemapEntry'),
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 404,
            description: 'Такого файла нет',
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
    ],
)]
final readonly class SitemapFileController
{
    public function __construct(private SitemapFeed $feed)
    {
    }

    public function __invoke(string $id): JsonResponse
    {
        $entries = $this->feed->file($id);

        if ($entries === null) {
            return new JsonResponse(
                data: ErrorCode::NOT_FOUND->body((string) __('cms-seo::seo.sitemap.not_found')),
                status: 404,
                options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        return new JsonResponse(
            data: ['data' => $entries],
            options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
