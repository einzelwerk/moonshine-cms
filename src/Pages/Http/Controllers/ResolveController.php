<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Controllers;

use Ewk\Cms\Pages\Http\Resolve\ResolveInput;
use Ewk\Cms\Pages\Http\Resolve\ResolvePage;
use Ewk\Cms\Pages\Http\Resolve\ResolveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/resolve`: one endpoint for every public content address, blocks
 * included.
 *
 * Three outcomes leave here. 200 carries the document in the `{data: …}`
 * envelope every endpoint of the CMS uses. 400 is the language prefix: a path
 * carrying a language code is an integration mistake, and a silent 404 would
 * hide it. 404 is everything else — an unknown address, a draft, a page taken
 * off publication — and it is written here rather than thrown, so that every
 * answer of the endpoint carries `Vary: Accept-Language`.
 *
 * A declared block parameter with a value it does not accept never arrives here
 * as an outcome: the validation exception of the parameter resolution travels
 * straight to the handler and becomes a 422. The same goes for a missing
 * `path`.
 *
 * The controller takes the request of the application itself and not a form
 * request of its own, see {@see ResolveInput}: the subject of a template page
 * has to land on the object everything downstream reads.
 */
#[OA\Get(
    path: '/v1/resolve',
    operationId: 'CmsResolveV1',
    description: 'Один вход на любой публичный контентный адрес: страница, её субъект, блоки снимка, '
        .'языковые версии и метаданные. Отдаётся опубликованный снимок, черновик — 404.',
    summary: 'Разрешить адрес',
    tags: ['CMS'],
    parameters: [
        new OA\Parameter(
            name: 'path',
            description: 'Путь без префикса языка, например `/` или `/tours/stand-up-action`. '
                .'Путь с кодом языка в первом сегменте — 400: язык приходит заголовком.',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: '/'),
        ),
        new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
        new OA\Parameter(
            name: 'b',
            description: 'Параметры динамических блоков, сгруппированные по якорю блока: '
                .'`b[<якорь>][<параметр>]=<значение>`, например `b[raspisanie][page]=2` или '
                .'`b[afisha][shown]=36&b[afisha][date]=2026-09-12`. Якорь берётся из `blocks[].anchor`, '
                .'набор параметров типа — из расширения `x-cms-params` его схемы `…BlockData`. '
                .'Скобки в именах ключей не кодируются. Набор под неизвестным якорем и необъявленный '
                .'параметр отбрасываются молча; недопустимое значение объявленного параметра — 422.',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'object', additionalProperties: true),
            style: 'deepObject',
            explode: true,
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Адрес разрешён',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Представление зависит от языка запроса',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/CmsResolveResponse'),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'Первый сегмент пути — код языка: язык приходит заголовком Accept-Language',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Представление зависит от языка запроса',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
        new OA\Response(
            response: 404,
            description: 'Адрес не найден, страница не опубликована или снята с публикации',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Представление зависит от языка запроса',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
        new OA\Response(
            response: 422,
            description: 'Путь не передан или недопустимое значение объявленного параметра блока',
            content: new OA\JsonContent(ref: '#/components/schemas/CmsValidationError'),
        ),
    ],
)]
final readonly class ResolveController
{
    public function __construct(private ResolvePage $resolve)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $outcome = $this->resolve->resolve(ResolveInput::path($request), $request);

        if ($outcome->status === ResolveStatus::LANGUAGE_PREFIX) {
            return self::answer([
                'message' => (string) __('cms-pages::resolve.language_prefix', [
                    'language' => (string) $outcome->language,
                ]),
            ], 400);
        }

        if (! $outcome->isFound() || $outcome->document === null) {
            return self::answer(['message' => (string) __('cms-pages::resolve.not_found')], 404);
        }

        return self::answer(['data' => $outcome->document->toArray()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function answer(array $payload, int $status = 200): JsonResponse
    {
        // This is not caching: the header says what the representation depends
        // on, and without it the first intermediary on the way hands one
        // language to everybody. A miss depends on the language just as a hit
        // does — the entity behind the address may be published in one language
        // only — so the header belongs on the 404 too.
        return new JsonResponse(
            data: $payload,
            status: $status,
            headers: ['Vary' => 'Accept-Language'],
            options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
