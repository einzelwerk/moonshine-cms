<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Controllers;

use Ewk\Cms\Blocks\Http\ErrorCode;
use Ewk\Cms\Blocks\Params\BlockParameters;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Pages\Http\Resolve\ResolveInput;
use Ewk\Cms\Pages\Http\Resolve\ResolvePage;
use Ewk\Cms\Pages\Http\Resolve\ResolveStatus;
use Ewk\Cms\Pages\Http\Resolve\SnapshotAnchor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/blocks/{uuid}`: the data of one block, for a list that grows on
 * the client and for a block that redraws apart from its page.
 *
 * The block is not looked up by its uuid alone. The page of `path` is resolved
 * by the very same rules as the whole page resolver, its snapshot is read, and
 * the uuid is looked for inside that snapshot: a block of another page answers
 * 404 and its content is not disclosed.
 *
 * Every miss arrives as `null` from the renderer — no snapshot, an owner
 * switched off, a uuid outside this snapshot, a block switched off, an
 * unregistered type, a block that is not dynamic — and all of them become one
 * 404, so the endpoint tells an outsider nothing about which of the six it was.
 *
 * The controller takes the request of the application itself and not a form
 * request of its own, see {@see ResolveInput}.
 */
#[OA\Get(
    path: '/v1/blocks/{uuid}',
    operationId: 'CmsShowBlockV1',
    description: 'Блок ищется в опубликованном снимке страницы `path`. Параметры блока из `x-cms-params` '
        .'его схемы `…BlockData` передаются плоскими ключами (`?month=2026-01`); необъявленный '
        .'отбрасывается, недопустимое значение — 422.',
    summary: 'Данные одного блока',
    tags: ['CMS'],
    parameters: [
        new OA\Parameter(
            name: 'uuid',
            description: 'UUID блока из `blocks[].uuid`',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid'),
        ),
        new OA\Parameter(
            name: 'path',
            description: 'Путь страницы блока; блок другой страницы — 404',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: '/tours/stand-up-action'),
        ),
        new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
        new OA\Parameter(
            name: 'page',
            description: 'Номер страницы в режиме пагинации `pages`',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1),
        ),
        new OA\Parameter(
            name: 'per_page',
            description: 'Размер страницы в режиме `pages`, если схема блока разрешает; сверх предела — 422',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1),
        ),
        new OA\Parameter(
            name: 'shown',
            description: 'Показанный срез в режиме `more` через адрес страницы; приводится к кратному порции и потолку',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1),
        ),
        new OA\Parameter(
            name: 'cursor',
            description: 'Курсор в режиме `more` через этот endpoint',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Конверт блока',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Ответ зависит от Accept-Language',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsBlockResponse'),
        ),
        new OA\Response(
            response: 400,
            description: 'Путь начинается с кода языка',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Ответ зависит от Accept-Language',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
        new OA\Response(
            response: 404,
            description: 'Страница не опубликована или блока нет в её снимке',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Ответ зависит от Accept-Language',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
        new OA\Response(
            response: 422,
            description: 'Нет пути или недопустимое значение параметра блока',
            content: new OA\JsonContent(ref: '#/components/schemas/CmsValidationError'),
        ),
        new OA\Response(
            response: 429,
            description: 'Превышен лимит запросов',
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
    ],
)]
final readonly class ShowBlockController
{
    public function __construct(
        private ResolvePage $resolve,
        private BlockRenderer $renderer,
        private SnapshotAnchor $anchors,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $outcome = $this->resolve->locate(ResolveInput::path($request), $request);

        if ($outcome->status === ResolveStatus::LANGUAGE_PREFIX) {
            return self::answer(ErrorCode::BAD_REQUEST->body((string) __('cms-pages::resolve.language_prefix', [
                'language' => (string) $outcome->language,
            ])), 400);
        }

        $located = $outcome->located;

        if (! $outcome->isFound() || $located === null) {
            return self::answer(ErrorCode::NOT_FOUND->body((string) __('cms-pages::resolve.not_found')), 404);
        }

        // The namespace of the parameters is the anchor of the block as the
        // snapshot holds it, not one the client could name himself.
        $anchor = $this->anchors->of($located->version, $uuid);

        $envelope = $this->renderer->renderPublishedBlock(
            $located->page,
            $uuid,
            $request,
            BlockParameters::forSingle($anchor ?? '', ResolveInput::blockQuery($request)),
        );

        if ($envelope === null) {
            return self::answer(ErrorCode::NOT_FOUND->body((string) __('cms-pages::resolve.block_not_found')), 404);
        }

        return self::answer(['data' => $envelope]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function answer(array $payload, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            data: $payload,
            status: $status,
            headers: ['Vary' => 'Accept-Language'],
            options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
