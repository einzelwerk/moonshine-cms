<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Http\Controllers;

use Ewk\Cms\Seo\Sitemap\SitemapFeed;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/sitemap`: the names of the files of the sitemap. The frontend
 * returns them from `generateSitemaps()` and writes the sitemap index out of
 * them; Next builds no index of its own.
 *
 * The answer does not depend on the language of the request: every file is
 * named, every language included.
 */
#[OA\Get(
    path: '/v1/sitemap',
    operationId: 'CmsSitemapIndexV1',
    description: 'Файл — один язык и один источник адресов (`ru-event-1`), на одноязычном сайте без префикса '
        .'языка — только источник (`event-1`). Ответ не зависит от языка запроса.',
    summary: 'Файлы карты сайта',
    tags: ['CMS'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Имена файлов в постоянном порядке',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/CmsSitemapFile'),
                    ),
                ],
            ),
        ),
    ],
)]
final readonly class SitemapIndexController
{
    public function __construct(private SitemapFeed $feed)
    {
    }

    public function __invoke(): JsonResponse
    {
        $files = [];

        foreach ($this->feed->files() as $id) {
            $files[] = ['id' => $id];
        }

        return new JsonResponse(
            data: ['data' => $files],
            options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
