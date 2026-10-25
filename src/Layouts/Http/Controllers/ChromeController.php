<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Http\Controllers;

use Ewk\Cms\Layouts\Chrome\ChromeDocument;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/chrome`: everything around the content in one document — every
 * active layout, header, footer and every menu reachable through one of their
 * slots.
 *
 * A separate request and not part of the block response on purpose. The
 * chrome is identical on thousands of pages, so embedding it would mean the
 * same kilobytes in every answer, and the request is not sequential: the
 * frontend asks for the chrome in parallel with resolving the address.
 *
 * **The answer is the same for every visitor**. The service takes a language
 * and nothing else — no request, no user, no session — so nothing personal can
 * leak into it; a personal chrome would turn a response shared by thousands of
 * pages into a response cached per token.
 */
#[OA\Get(
    path: '/v1/chrome',
    operationId: 'CmsChromeV1',
    description: 'Ответ одинаков для всех посетителей.',
    summary: 'Обвязка сайта',
    tags: ['CMS'],
    security: [],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Обвязка сайта',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Ответ зависит от Accept-Language',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Chrome'),
                ],
            ),
        ),
    ],
)]
final readonly class ChromeController
{
    public function __construct(private ChromeDocument $chrome)
    {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            data: ['data' => $this->chrome->build()],
            headers: ['Vary' => 'Accept-Language'],
            options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
