<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use Ewk\Cms\Blocks\Http\ErrorCode;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use OpenApi\Attributes as OA;

/**
 * The components every block of the CMS may point at: the two values that
 * travel inside a payload (media and a link) and the two shapes a dynamic
 * block adds to its own `data` (pagination and filters).
 *
 * They are built here and not written as attribute carriers because their
 * vocabularies live in PHP: the kinds of a link, the modes of the pagination
 * and its transports are enumerations, and repeating their cases in a doc
 * comment is how a generated contract starts drifting from the code.
 */
final class CommonSchemas
{
    public const string MEDIA = 'CmsMedia';

    public const string MEDIA_REF = 'CmsMediaRef';

    public const string LINK = 'CmsLink';

    public const string PAGINATION = 'CmsPagination';

    public const string PAGINATION_PAGES = 'CmsPaginationPages';

    public const string PAGINATION_MORE = 'CmsPaginationMore';

    public const string PAGINATION_NONE = 'CmsPaginationNone';

    public const string FILTERS = 'CmsFilters';

    public const string ERROR = 'CmsError';

    public const string VALIDATION_ERROR = 'CmsValidationError';

    public const string ACCEPT_LANGUAGE = 'AcceptLanguage';

    public const string RESOLVE_EXTENSION = 'CmsResolveExtension';

    public static function ref(string $component): string
    {
        return '#/components/schemas/'.$component;
    }

    /**
     * @return list<OA\Schema>
     */
    public static function schemas(): array
    {
        return [
            self::media(),
            self::mediaRef(),
            self::link(),
            self::paginationPages(),
            self::paginationMore(),
            self::paginationNone(),
            self::pagination(),
            self::filters(),
            self::error(),
            self::validationError(),
        ];
    }

    /**
     * Components the CMS points at but does not own: what is returned here
     * is a placeholder, and an installation that declares the name itself
     * keeps its own declaration. Unlike {@see self::schemas()}, a name already
     * taken is not a conflict here — it is the whole point.
     *
     * @return list<OA\Schema>
     */
    public static function fallbackSchemas(): array
    {
        return [
            self::resolveExtension(),
        ];
    }

    /**
     * What an installation adds to the answer of the resolver next to the keys
     * of the CMS. A contributor of the application writes those keys, see
     * `ResolveContributor` of the pages, and the core cannot know them, so the
     * answer takes this component into its `allOf` and the placeholder here is
     * an open object with nothing required. An installation that writes keys of
     * its own declares the component itself and lists them.
     */
    private static function resolveExtension(): OA\Schema
    {
        return new OA\Schema(
            schema: self::RESOLVE_EXTENSION,
            description: 'Ключи ответа резолвера, которые дописывает приложение',
            type: 'object',
            additionalProperties: true,
        );
    }

    /**
     * The refusal of an endpoint of the CMS: a top-level `message` without
     * the `data` envelope of a success, so the two do not overlap and a client
     * narrows between them without a runtime type guard.
     *
     * Declared by this package and not borrowed from the application: the
     * endpoints live in the packages, so the shape of their answers has to
     * travel with them. It is built here rather than written as a scanned
     * attribute, because two packages point at it and two identical
     * declarations under one component name would be a silent race between
     * whichever file the scanner reached first.
     */
    private static function error(): OA\Schema
    {
        return new OA\Schema(
            schema: self::ERROR,
            description: 'Ошибка',
            required: ['message', 'code'],
            properties: [
                new OA\Property(property: 'message', description: 'Сообщение для пользователя', type: 'string', example: 'Не найдено.'),
                new OA\Property(
                    property: 'code',
                    description: 'Код ошибки',
                    type: 'string',
                    enum: [ErrorCode::BAD_REQUEST->value, ErrorCode::NOT_FOUND->value, ErrorCode::TOO_MANY_REQUESTS->value],
                ),
                new OA\Property(
                    property: 'retry_after',
                    description: 'Секунд до снятия лимита; только у 429',
                    type: 'integer',
                    nullable: true,
                    example: 42,
                ),
            ],
            type: 'object',
        );
    }

    private static function validationError(): OA\Schema
    {
        return new OA\Schema(
            schema: self::VALIDATION_ERROR,
            description: 'Ошибка валидации',
            required: ['message', 'code', 'errors'],
            properties: [
                new OA\Property(property: 'message', description: 'Сообщение для пользователя', type: 'string'),
                new OA\Property(
                    property: 'code',
                    description: 'Код ошибки',
                    type: 'string',
                    enum: [ErrorCode::VALIDATION_FAILED->value],
                ),
                new OA\Property(
                    property: 'errors',
                    description: 'Ошибки по полям',
                    type: 'object',
                    example: ['path' => ['The path field is required.']],
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                    ),
                ),
            ],
            type: 'object',
        );
    }

    /**
     * The language of a content response.
     *
     * A deliberate departure from "the language is the business of the
     * middleware and does not show in the specification": what is not in the
     * specification is not in the generated client either, and the frontend
     * then keeps setting the header by hand. Mandatory while the catalog holds
     * more than one language, optional while it holds one.
     */
    public static function acceptLanguage(LanguageCatalog $languages): OA\Parameter
    {
        $locales = $languages->locales();

        return new OA\Parameter(
            parameter: self::ACCEPT_LANGUAGE,
            name: 'Accept-Language',
            description: 'Язык ответа с учётом q-приоритетов; регион отбрасывается (en-US → en), '
                .'без подходящего языка — язык по умолчанию',
            in: 'header',
            required: \count($locales) > 1,
            schema: new OA\Schema(type: 'string', example: $locales[0] ?? 'ru'),
        );
    }

    /**
     * One shape of a picture for every block, whatever its origin. Unknown
     * dimensions arrive as `null` and are never guessed.
     *
     * `kind`, `mime` and `size` say what the file is. The disk, the path and
     * the checksum are not here and never will be: a reader gets an address,
     * not the layout of a storage.
     */
    private static function media(): OA\Schema
    {
        return new OA\Schema(
            schema: self::MEDIA,
            description: 'Файл медиатеки в единой форме',
            required: ['id', 'url', 'thumb_url', 'width', 'height', 'alt', 'kind', 'mime', 'size'],
            properties: [
                new OA\Property(property: 'id', description: 'UUID записи медиатеки', type: 'string', nullable: true),
                new OA\Property(property: 'url', description: 'Адрес файла', type: 'string', format: 'uri'),
                new OA\Property(property: 'thumb_url', description: 'Адрес превью; null — превью не создано', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'width', description: 'Ширина в пикселях; null — неизвестна', type: 'integer', nullable: true),
                new OA\Property(property: 'height', description: 'Высота в пикселях; null — неизвестна', type: 'integer', nullable: true),
                new OA\Property(property: 'alt', description: 'Альтернативный текст на языке ответа', type: 'string', nullable: true),
                new OA\Property(property: 'kind', description: 'Вид файла', type: 'string', enum: ['image', 'vector', 'video', 'audio', 'document']),
                new OA\Property(property: 'mime', description: 'MIME-тип', type: 'string'),
                new OA\Property(property: 'size', description: 'Размер в байтах', type: 'integer'),
            ],
            type: 'object',
        );
    }

    /**
     * What a media field of a block holds and, unless the resource hydrates it,
     * what leaves in the payload: a reference to a record of the library. Never
     * a path and never a ready address.
     *
     * `CmsMedia` is the hydrated form; a block type that wants it says so by
     * describing its payload itself, see {@see DescribesPayloadSchema}. The
     * generator does not promise it on behalf of a resource that does not do
     * the hydrating.
     */
    private static function mediaRef(): OA\Schema
    {
        return new OA\Schema(
            schema: self::MEDIA_REF,
            description: 'Ссылка на запись медиатеки без адреса файла',
            required: ['id'],
            properties: [
                new OA\Property(property: 'id', description: 'UUID записи медиатеки', type: 'string', format: 'uuid'),
                new OA\Property(
                    property: 'metadata',
                    description: 'Переопределения metadata записи; `alt` — по языкам',
                    type: 'object',
                    additionalProperties: true,
                ),
            ],
            type: 'object',
        );
    }

    /**
     * A link already resolved into an address. `href: null` means the target is
     * unreachable; the kind stays, so the block decides what to draw instead of
     * receiving a broken address.
     */
    private static function link(): OA\Schema
    {
        return new OA\Schema(
            schema: self::LINK,
            description: 'Ссылка с готовым адресом; путь без префикса языка',
            required: ['kind', 'href', 'external', 'new_tab', 'action'],
            properties: [
                new OA\Property(property: 'kind', description: 'Вид ссылки', type: 'string', enum: array_column(LinkKind::cases(), 'value')),
                new OA\Property(property: 'href', description: 'Адрес; null — цель недостижима', type: 'string', nullable: true),
                new OA\Property(property: 'external', description: 'Внешний адрес', type: 'boolean'),
                new OA\Property(property: 'new_tab', description: 'Открывать в новой вкладке', type: 'boolean'),
                new OA\Property(
                    property: 'action',
                    description: 'Действие фронта; null у ссылки другого вида',
                    required: ['name', 'params'],
                    properties: [
                        new OA\Property(property: 'name', description: 'Код действия', type: 'string'),
                        new OA\Property(
                            property: 'params',
                            description: 'Параметры действия',
                            type: 'object',
                            additionalProperties: true,
                        ),
                    ],
                    type: 'object',
                    nullable: true,
                ),
            ],
            type: 'object',
        );
    }

    /**
     * Numbered pages. The links arrive ready and relative: the frontend neither
     * assembles the query string nor knows about the namespace of the anchor.
     * The first page is addressed without a number, so the address matches the
     * canonical one.
     */
    private static function paginationPages(): OA\Schema
    {
        return new OA\Schema(
            schema: self::PAGINATION_PAGES,
            description: 'Нумерованные страницы; позиция в адресе страницы',
            required: ['mode', 'per_page', 'current_page', 'last_page', 'total', 'has_more', 'links'],
            properties: [
                new OA\Property(property: 'mode', description: 'Режим пагинации', type: 'string', enum: [PaginationMode::PAGES->value]),
                new OA\Property(property: 'per_page', description: 'Размер страницы', type: 'integer', minimum: 1),
                new OA\Property(property: 'current_page', description: 'Номер текущей страницы', type: 'integer', minimum: 1),
                new OA\Property(property: 'last_page', description: 'Номер последней страницы', type: 'integer', minimum: 1),
                new OA\Property(property: 'total', description: 'Всего записей', type: 'integer', minimum: 0),
                new OA\Property(property: 'has_more', description: 'Есть следующая страница', type: 'boolean'),
                new OA\Property(
                    property: 'links',
                    description: 'Строки запроса соседних страниц; null — направления нет',
                    required: ['self', 'next', 'prev'],
                    properties: [
                        new OA\Property(property: 'self', description: 'Текущая страница', type: 'string'),
                        new OA\Property(property: 'next', description: 'Следующая страница', type: 'string', nullable: true),
                        new OA\Property(property: 'prev', description: 'Предыдущая страница', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        );
    }

    /**
     * The growing slice of «show more».
     *
     * `shown` is the size of the slice after normalization and not what came
     * in the address. `total` is absent in the cursor transport: a cursor
     * selection does not count the list. `has_more: true` with `next: null`
     * means one thing only — the list continues, but the ceiling of the block
     * has been reached, and `catalog` is where it continues.
     */
    private static function paginationMore(): OA\Schema
    {
        return new OA\Schema(
            schema: self::PAGINATION_MORE,
            description: 'Дозагрузка: `url` держит позицию в адресе страницы (`shown`), '
                .'`endpoint` — в курсоре запроса к endpoint-у блока',
            required: ['mode', 'continuation', 'per_page', 'has_more', 'next'],
            properties: [
                new OA\Property(property: 'mode', description: 'Режим пагинации', type: 'string', enum: [PaginationMode::MORE->value]),
                new OA\Property(property: 'continuation', description: 'Способ продолжения', type: 'string', enum: array_column(Continuation::cases(), 'value')),
                new OA\Property(property: 'per_page', description: 'Размер порции', type: 'integer', minimum: 1),
                new OA\Property(property: 'shown', description: 'Размер показанного среза; только для `url`', type: 'integer', minimum: 1),
                new OA\Property(property: 'total', description: 'Всего записей; только для `url`', type: 'integer', minimum: 0),
                new OA\Property(property: 'has_more', description: 'Список продолжается', type: 'boolean'),
                new OA\Property(property: 'next', description: 'Строка запроса страницы или адрес endpoint-а блока со следующей порцией; null — потолок блока', type: 'string', nullable: true),
                new OA\Property(
                    property: 'catalog',
                    description: 'Каталог за потолком блока; показывается, когда `next` пуст',
                    required: ['href'],
                    properties: [
                        new OA\Property(property: 'href', description: 'Адрес', type: 'string'),
                    ],
                    type: 'object',
                    nullable: true,
                ),
            ],
            type: 'object',
        );
    }

    private static function paginationNone(): OA\Schema
    {
        return new OA\Schema(
            schema: self::PAGINATION_NONE,
            description: 'Список без продолжения',
            required: ['mode'],
            properties: [
                new OA\Property(property: 'mode', description: 'Режим пагинации', type: 'string', enum: [PaginationMode::NONE->value]),
            ],
            type: 'object',
        );
    }

    private static function pagination(): OA\Schema
    {
        return new OA\Schema(
            schema: self::PAGINATION,
            description: 'Пагинация; форма зависит от `mode`',
            discriminator: new OA\Discriminator(
                propertyName: 'mode',
                mapping: [
                    PaginationMode::PAGES->value => self::ref(self::PAGINATION_PAGES),
                    PaginationMode::MORE->value => self::ref(self::PAGINATION_MORE),
                    PaginationMode::NONE->value => self::ref(self::PAGINATION_NONE),
                ],
            ),
            oneOf: [
                new OA\Schema(ref: self::ref(self::PAGINATION_PAGES)),
                new OA\Schema(ref: self::ref(self::PAGINATION_MORE)),
                new OA\Schema(ref: self::ref(self::PAGINATION_NONE)),
            ],
        );
    }

    /**
     * The declared parameter and the available values of a filter are two
     * different things: the schema declares the parameter, the visitor passes
     * the chosen value, and the block computes the options out of its own data.
     */
    private static function filters(): OA\Schema
    {
        return new OA\Schema(
            schema: self::FILTERS,
            description: 'Панель фильтров: `inline` фильтрует этот список, `redirect` ведёт на каталог по `redirect`',
            required: ['mode', 'fields'],
            properties: [
                new OA\Property(property: 'mode', description: 'Режим панели', type: 'string', enum: ['inline', 'redirect']),
                new OA\Property(
                    property: 'fields',
                    description: 'Имя параметра → выбранное значение и варианты',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        required: ['value', 'options'],
                        properties: [
                            new OA\Property(property: 'value', description: 'Выбранное значение; null — не выбрано', type: 'string', nullable: true),
                            new OA\Property(
                                property: 'options',
                                description: 'Варианты',
                                type: 'array',
                                items: new OA\Items(
                                    required: ['value', 'label'],
                                    properties: [
                                        new OA\Property(property: 'value', description: 'Значение; null — без фильтра', type: 'string', nullable: true),
                                        new OA\Property(property: 'label', description: 'Подпись', type: 'string'),
                                    ],
                                    type: 'object',
                                ),
                            ),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Property(
                    property: 'redirect',
                    description: 'Цель режима `redirect`',
                    required: ['href'],
                    properties: [
                        new OA\Property(property: 'href', description: 'Адрес', type: 'string'),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        );
    }
}
