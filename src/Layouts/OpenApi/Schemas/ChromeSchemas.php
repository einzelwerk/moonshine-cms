<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * The shape of `GET /v1/chrome` and `GET /v1/menus/{code}`.
 *
 * The dictionary of slots travels into the contract as an enumeration: every
 * slot is a place in the markup of the frontend, and an open list would oblige
 * it to draw a place it does not know.
 *
 * There is deliberately nothing personal here: the answer is the same for every
 * visitor.
 */
#[OA\Schema(
    schema: 'ChromeLayout',
    required: ['code', 'name', 'is_default'],
    properties: [
        new OA\Property(property: 'code', description: 'Код оболочки, которому во фронте соответствует компонент', type: 'string', example: 'default'),
        new OA\Property(property: 'name', type: 'string', example: 'Стандартный'),
        new OA\Property(property: 'container', type: 'string', example: 'wide', nullable: true),
        new OA\Property(property: 'body_class', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', type: 'boolean'),
        new OA\Property(property: 'header', description: 'Код шапки этого лейаута', type: 'string', nullable: true),
        new OA\Property(property: 'footer', description: 'Код подвала этого лейаута', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromePart',
    description: 'Шапка или подвал: значения объявленных полей и меню, разложенные по слотам',
    required: ['code', 'name', 'content', 'slots'],
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'default'),
        new OA\Property(property: 'name', type: 'string', example: 'Основной подвал'),
        new OA\Property(property: 'content', description: 'Значения полей, объявленных схемой шапки или подвала', type: 'object', additionalProperties: true),
        new OA\Property(
            property: 'slots',
            // The dictionary of slots is closed: every slot is a place in the
            // markup of the frontend, and an open list would oblige it to draw
            // a place it does not know. MenuSlotsProcessor puts in a property
            // per code when the specification is built: the set is known only
            // at runtime, because an installation extends it by configuration.
            description: 'Коды меню, разложенные по слотам. Свойство на каждый слот установки, '
                .'словарь закрыт: ключа вне него не бывает.',
            type: 'object',
            example: ['footer_1' => ['footer_1']],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromeMenuItem',
    required: ['label', 'link', 'broken', 'children'],
    properties: [
        new OA\Property(property: 'label', type: 'string', example: 'Условия использования'),
        new OA\Property(property: 'link', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/CmsLink')]),
        new OA\Property(property: 'broken', description: 'Цель не разрешилась: адрес пустой, пункт всё равно отдан', type: 'boolean'),
        new OA\Property(property: 'icon', type: 'string', nullable: true),
        new OA\Property(property: 'variant', type: 'string', nullable: true),
        new OA\Property(
            property: 'children',
            description: 'Второй и последний уровень меню',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ChromeMenuItem'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromeMenu',
    required: ['code', 'name', 'source', 'items'],
    properties: [
        new OA\Property(property: 'code', description: 'Код меню, по нему же работает GET /v1/menus/{code}', type: 'string', example: 'footer_1'),
        new OA\Property(property: 'name', type: 'string', example: 'Подвал, первая колонка'),
        new OA\Property(property: 'source', description: 'Откуда взяты пункты: вручную или из справочника', type: 'string', example: 'manual'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeMenuItem')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Chrome',
    description: 'Обвязка сайта: лейауты, шапки, подвалы и меню одного языка. Ответ одинаков для всех посетителей.',
    required: ['locale', 'layouts', 'headers', 'footers', 'menus'],
    properties: [
        new OA\Property(property: 'locale', type: 'string', example: 'ru'),
        new OA\Property(property: 'layouts', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeLayout')),
        new OA\Property(property: 'headers', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromePart')),
        new OA\Property(property: 'footers', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromePart')),
        new OA\Property(property: 'menus', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeMenu')),
    ],
    type: 'object',
)]
final class ChromeSchemas
{
}
