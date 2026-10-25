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
    description: 'Лейаут: оболочка страницы с шапкой и подвалом',
    required: ['code', 'name', 'is_default'],
    properties: [
        new OA\Property(property: 'code', description: 'Код лейаута; фронт сопоставляет ему компонент', type: 'string', example: 'default'),
        new OA\Property(property: 'name', description: 'Название', type: 'string', example: 'Стандартный'),
        new OA\Property(property: 'container', description: 'Ширина контейнера', type: 'string', example: 'wide', nullable: true),
        new OA\Property(property: 'body_class', description: 'CSS-класс элемента body', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', description: 'Лейаут по умолчанию', type: 'boolean'),
        new OA\Property(property: 'header', description: 'Код шапки; null — без шапки', type: 'string', nullable: true),
        new OA\Property(property: 'footer', description: 'Код подвала; null — без подвала', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromePart',
    description: 'Шапка или подвал: значения полей и меню по слотам',
    required: ['code', 'name', 'content', 'slots'],
    properties: [
        new OA\Property(property: 'code', description: 'Код шапки или подвала', type: 'string', example: 'default'),
        new OA\Property(property: 'name', description: 'Название', type: 'string', example: 'Основной подвал'),
        new OA\Property(property: 'content', description: 'Значения полей, объявленных схемой шапки или подвала', type: 'object', additionalProperties: true),
        new OA\Property(
            property: 'slots',
            // The dictionary of slots is closed: every slot is a place in the
            // markup of the frontend, and an open list would oblige it to draw
            // a place it does not know. MenuSlotsProcessor puts in a property
            // per code when the specification is built: the set is known only
            // at runtime, because an installation extends it by configuration.
            description: 'Коды меню по слотам; ключи — только слоты установки',
            type: 'object',
            example: ['footer_1' => ['footer_1']],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromeMenuItem',
    description: 'Пункт меню',
    required: ['label', 'link', 'broken', 'children'],
    properties: [
        new OA\Property(property: 'label', description: 'Подпись', type: 'string', example: 'Условия использования'),
        new OA\Property(property: 'link', description: 'Ссылка; null — пункт без ссылки', type: 'object', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/CmsLink')]),
        new OA\Property(property: 'broken', description: 'Цель ссылки не разрешилась: адреса нет, пункт всё равно отдан', type: 'boolean'),
        new OA\Property(property: 'icon', description: 'Иконка', type: 'string', nullable: true),
        new OA\Property(property: 'variant', description: 'Вариант оформления', type: 'string', nullable: true),
        new OA\Property(
            property: 'children',
            description: 'Пункты второго уровня; глубже уровней нет',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ChromeMenuItem'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ChromeMenu',
    description: 'Меню',
    required: ['code', 'name', 'source', 'items'],
    properties: [
        new OA\Property(property: 'code', description: 'Код меню для GET /v1/menus/{code}', type: 'string', example: 'footer_1'),
        new OA\Property(property: 'name', description: 'Название', type: 'string', example: 'Подвал, первая колонка'),
        new OA\Property(property: 'source', description: 'Источник пунктов: вручную или из справочника', type: 'string', example: 'manual'),
        new OA\Property(property: 'items', description: 'Пункты меню', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeMenuItem')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Chrome',
    description: 'Обвязка сайта одного языка: лейауты, шапки, подвалы и меню',
    required: ['locale', 'layouts', 'headers', 'footers', 'menus'],
    properties: [
        new OA\Property(property: 'locale', description: 'Язык ответа (ISO 639-1)', type: 'string', example: 'ru'),
        new OA\Property(property: 'layouts', description: 'Активные лейауты', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeLayout')),
        new OA\Property(property: 'headers', description: 'Шапки', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromePart')),
        new OA\Property(property: 'footers', description: 'Подвалы', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromePart')),
        new OA\Property(property: 'menus', description: 'Меню, достижимые через слоты', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChromeMenu')),
    ],
    type: 'object',
)]
final class ChromeSchemas
{
}
