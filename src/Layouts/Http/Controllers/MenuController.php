<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Http\Controllers;

use Ewk\Cms\Blocks\Http\ErrorCode;
use Ewk\Cms\Layouts\Chrome\ChromeDocument;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * `GET /api/v1/menus/{code}`, for the case where the whole chrome is more than
 * the caller needs.
 *
 * A menu that is switched off answers exactly like a menu that does not exist.
 * Activity is the only publication lever the chrome has, so a deactivated menu
 * must not stay reachable through a direct address.
 *
 * The miss is written here rather than thrown, so that every answer of the
 * endpoint carries `Vary: Accept-Language`: a menu depends on the language
 * just as much when it is absent as when it is there.
 */
#[OA\Get(
    path: '/v1/menus/{code}',
    operationId: 'CmsMenuV1',
    description: 'Одно меню, когда вся обвязка вызывающему не нужна. Выключенное меню отвечает так же, '
        .'как несуществующее: активность — единственный рычаг публикации обвязки.',
    summary: 'Меню по коду',
    tags: ['CMS'],
    parameters: [
        new OA\Parameter(
            name: 'code',
            description: 'Код меню из `menus[].code` документа обвязки',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', example: 'footer_1'),
        ),
        new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Меню',
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
                    new OA\Property(property: 'data', ref: '#/components/schemas/ChromeMenu'),
                ],
            ),
        ),
        new OA\Response(
            response: 404,
            description: 'Меню не найдено или выключено',
            headers: [
                new OA\Header(
                    header: 'Vary',
                    description: 'Представление зависит от языка запроса',
                    schema: new OA\Schema(type: 'string', example: 'Accept-Language'),
                ),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/CmsError'),
        ),
    ],
)]
final readonly class MenuController
{
    public function __construct(private ChromeDocument $chrome)
    {
    }

    public function __invoke(string $code): JsonResponse
    {
        $menu = $this->chrome->menu($code);

        if ($menu === null) {
            return self::answer(ErrorCode::NOT_FOUND->body((string) __('cms-layouts::chrome.menu_not_found')), 404);
        }

        return self::answer(['data' => $menu]);
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
