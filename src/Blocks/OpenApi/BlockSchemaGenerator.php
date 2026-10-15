<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Params\BooleanParam;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\IntegerParam;
use Ewk\Cms\Blocks\Params\Pagination;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\Param;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Illuminate\Support\Str;
use OpenApi\Annotations\Schema as AnnotationSchema;
use OpenApi\Attributes as OA;
use OpenApi\Undefined;

/**
 * The contract of the blocks, generated out of the registry of types and their
 * declarations rather than written by hand.
 *
 * One envelope per type, one payload per type, one discriminated union of them
 * all. The `code` of every variant stays a single-value enum: type narrowing on
 * the frontend does not run on `discriminator` — orval throws it away — it runs
 * because that enum becomes a literal. Drop it and `switch (block.code)` stops
 * compiling.
 */
final readonly class BlockSchemaGenerator
{
    public const string BLOCK = 'CmsBlock';

    public const string CHILDREN = 'CmsBlockChildren';

    public function __construct(
        private BlockRegistryInterface $registry,
        private FieldSchemaMapper $fields = new FieldSchemaMapper(),
    ) {
    }

    /**
     * Every component the blocks of this installation need: the union, the
     * recursive map of children and a pair of schemas per type.
     *
     * @return list<AnnotationSchema>
     */
    public function schemas(): array
    {
        $schemas = [];
        $mapping = [];

        foreach ($this->codes() as $code) {
            $declaration = $this->registry->schemaFor($code);
            $envelope = self::envelopeName($code);

            $schemas[] = $this->envelope($code, $declaration);
            $schemas[] = $this->payload($code, $declaration);

            $mapping[$code] = CommonSchemas::ref($envelope);
        }

        return [$this->union($mapping), $this->children(), ...$schemas];
    }

    /**
     * The codes that can appear in a response, in the order of the registry.
     *
     * The insertion of a shared block is not one of them: in its place the tree
     * of the original is rendered, so a `shared` variant in the union would
     * oblige the frontend to handle a code that never arrives.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = [];

        foreach (array_keys($this->registry->all()) as $code) {
            if ($code !== SharedInsertionBlock::code()) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public static function envelopeName(string $code): string
    {
        return Str::studly($code).'Block';
    }

    public static function payloadName(string $code): string
    {
        return self::envelopeName($code).'Data';
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function union(array $mapping): OA\Schema
    {
        $variants = [];

        foreach ($mapping as $ref) {
            $variants[] = new OA\Schema(ref: $ref);
        }

        return new OA\Schema(
            schema: self::BLOCK,
            description: 'Блок страницы. Тип сужается по `code`: у каждого варианта это перечисление '
                .'из одного значения, совпадающее с кодом реестра.',
            discriminator: new OA\Discriminator(propertyName: 'code', mapping: $mapping),
            oneOf: $variants,
        );
    }

    private function children(): OA\Schema
    {
        return new OA\Schema(
            schema: self::CHILDREN,
            description: 'Дети контейнера по слотам: ключ — имя слота, значение — блоки в порядке '
                .'редактора. У блока без слотов приезжает пустая карта.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(ref: CommonSchemas::ref(self::BLOCK)),
            ),
        );
    }

    /**
     * The envelope `{uuid, code, variant, anchor, data, children}` that
     * `BlockRenderer` builds. The autoincrement id never leaves the backend,
     * the uuid is the external handle of a block.
     */
    private function envelope(string $code, BlockSchema $declaration): OA\Schema
    {
        $variants = array_map(
            static fn (int|string $key): string => (string) $key,
            array_keys($declaration->variants()),
        );

        return new OA\Schema(
            schema: self::envelopeName($code),
            description: $declaration->title() === '' ? Undefined::UNDEFINED : $declaration->title(),
            required: ['uuid', 'code', 'variant', 'anchor', 'data', 'children'],
            properties: [
                new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                new OA\Property(property: 'code', type: 'string', enum: [$code]),
                new OA\Property(
                    property: 'variant',
                    description: 'Оформление блока',
                    type: 'string',
                    // `null` is a member of the enumeration, not only
                    // `nullable`: a block without a chosen variant answers
                    // null, and in OpenAPI 3.0 an optional enumeration has to
                    // list it.
                    enum: $variants === [] ? null : [...$variants, null],
                    nullable: true,
                ),
                new OA\Property(
                    property: 'anchor',
                    description: $declaration->isDynamic()
                        ? 'Якорь блока; он же ключ неймспейса параметров в адресе страницы'
                        : 'Якорь блока для оглавления и ссылок',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(property: 'data', ref: CommonSchemas::ref(self::payloadName($code))),
                new OA\Property(property: 'children', ref: CommonSchemas::ref(self::CHILDREN)),
            ],
            type: 'object',
        );
    }

    /**
     * The payload of one type. A resource that composes it out of a domain
     * selection describes it itself, see {@see DescribesPayloadSchema};
     * otherwise it is the declared fields, plus the two shapes a dynamic block
     * adds to its own `data`.
     */
    private function payload(string $code, BlockSchema $declaration): AnnotationSchema
    {
        $resource = $this->registry->make($code)->resource();

        $schema = is_subclass_of($resource, DescribesPayloadSchema::class)
            ? $resource::payloadSchema()
            : $this->payloadOfFields($declaration);

        $schema->schema = self::payloadName($code);

        if ($declaration->isDynamic()) {
            // The anchor is data and not schema, so the parameters of a
            // dynamic block cannot be query parameters of a shared operation.
            // They travel with the type that declares them instead, and the
            // description of the endpoint says how to spell them.
            $schema->x = ['cms-params' => $this->parameters($declaration)];
        }

        return $schema;
    }

    private function payloadOfFields(BlockSchema $declaration): AnnotationSchema
    {
        $fields = $declaration->fields();
        $properties = $this->fields->properties($fields);
        $required = FieldSchemaMapper::names($fields);

        $pagination = $declaration->pagination();

        if ($pagination !== null) {
            $properties[] = self::paginationProperty($pagination);
            $required[] = 'pagination';
        }

        if (self::hasFilters($declaration)) {
            // Not required: a block draws the panel only where the visitor has
            // something to narrow.
            $properties[] = new OA\Property(
                property: 'filters',
                ref: CommonSchemas::ref(CommonSchemas::FILTERS),
            );
        }

        return new OA\Schema(
            // An empty `required` is not valid OpenAPI, and a type without
            // fields is a real case: `toc` builds its payload out of the
            // anchors of the page rather than out of stored values.
            required: $required === [] ? null : $required,
            properties: $properties === [] ? null : $properties,
            type: 'object',
        );
    }

    /**
     * The pagination of a payload, as narrow as the declaration allows.
     *
     * A fixed mode is described by its own shape and gives the frontend one
     * less branch. A mode the editor may change is a union — but only of the
     * modes he may pick: a schedule that allows `more` and `none` must not
     * promise numbered pages. The full `CmsPagination` stays for a
     * declaration that allows every mode.
     */
    public static function paginationProperty(?Pagination $pagination): OA\Property
    {
        // A type without a declared continuation answers `{mode: 'none'}`:
        // the key is in the payload, with a single branch.
        if ($pagination === null) {
            return new OA\Property(
                property: 'pagination',
                ref: CommonSchemas::ref(CommonSchemas::PAGINATION_NONE),
            );
        }

        if (! $pagination->modeEditable) {
            return new OA\Property(
                property: 'pagination',
                ref: CommonSchemas::ref(self::paginationComponent($pagination->mode)),
            );
        }

        if ($pagination->allowedModes === []) {
            return new OA\Property(
                property: 'pagination',
                ref: CommonSchemas::ref(CommonSchemas::PAGINATION),
            );
        }

        $mapping = [];
        $variants = [];

        foreach ($pagination->allowedModes as $mode) {
            $ref = CommonSchemas::ref(self::paginationComponent($mode));
            $mapping[$mode->value] = $ref;
            $variants[] = new OA\Schema(ref: $ref);
        }

        return new OA\Property(
            property: 'pagination',
            description: 'Форма размечена режимом; редактор выбирает его из разрешённых схемой.',
            discriminator: new OA\Discriminator(propertyName: 'mode', mapping: $mapping),
            oneOf: $variants,
        );
    }

    private static function paginationComponent(PaginationMode $mode): string
    {
        return match ($mode) {
            PaginationMode::PAGES => CommonSchemas::PAGINATION_PAGES,
            PaginationMode::MORE => CommonSchemas::PAGINATION_MORE,
            PaginationMode::NONE => CommonSchemas::PAGINATION_NONE,
        };
    }

    private static function hasFilters(BlockSchema $declaration): bool
    {
        foreach ($declaration->params() as $param) {
            if ($param->isFilter()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The parameters this type declares, as a vendor extension of its payload
     * schema: in the address they are namespaced by the anchor of the block
     * (`b[<anchor>][<name>]`), and the anchor is content, not contract.
     *
     * @return list<array<string, mixed>>
     */
    public function parameters(BlockSchema $declaration): array
    {
        $parameters = [];

        foreach ($declaration->params() as $param) {
            $parameters[] = self::parameter($param);
        }

        foreach (self::modeParameters($declaration->pagination()) as $parameter) {
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parameter(Param $param): array
    {
        $described = [
            'name' => $param->name(),
            'type' => self::parameterType($param),
            'filter' => $param->isFilter(),
        ];

        if ($param instanceof EnumParam) {
            $declared = $param->getOptionsDeclaration();

            // Only a set fixed by the declaration: options computed from live
            // data are not part of the contract, they arrive in `filters`.
            if (\is_array($declared) && $declared !== []) {
                $described['enum'] = array_map(
                    static fn (int|string $key): string => (string) $key,
                    array_keys($declared),
                );
            }
        }

        if ($param instanceof DateParam) {
            // `format` is a format of OpenAPI and not a `date()` string of
            // PHP: the latter travels under a key of its own, otherwise the
            // generator of the client would read «Y-m-d» as the name of a
            // format.
            $described['format'] = 'date';
            $described['date_format'] = $param->getFormat();
        }

        return $described;
    }

    private static function parameterType(Param $param): string
    {
        return match (true) {
            $param instanceof IntegerParam => 'integer',
            $param instanceof BooleanParam => 'boolean',
            $param instanceof DateParam, $param instanceof EnumParam, $param instanceof StringParam => 'string',
            default => 'string',
        };
    }

    /**
     * The parameters of the pagination itself are never listed by a schema: the
     * declared mode adds them.
     *
     * @return list<array<string, mixed>>
     */
    private static function modeParameters(?Pagination $pagination): array
    {
        if ($pagination === null) {
            return [];
        }

        $modes = [$pagination->mode];

        if ($pagination->modeEditable) {
            $modes = $pagination->allowedModes === []
                ? PaginationMode::cases()
                : $pagination->allowedModes;
        }

        $parameters = [];

        foreach ($modes as $mode) {
            foreach (self::parametersOfMode($mode, $pagination) as $name => $type) {
                $parameters[$name] = ['name' => $name, 'type' => $type, 'filter' => false];
            }
        }

        return array_values($parameters);
    }

    /**
     * The parameters the mode adds.
     *
     * `per_page` is listed for the numbered mode whatever `editable` says:
     * that flag governs the form of the editor, while the resolution takes the
     * parameter from the query either way and only clamps it by the effective
     * ceiling.
     *
     * The step of a growing slice is never taken from the query — the slice is
     * already given by the accumulator, and a free step would break its
     * multiplicity — so `more` gives the position, and which of the two it is
     * depends on the transport: the address of the page holds `shown`, the
     * endpoint of the block holds an opaque `cursor`.
     *
     * @return array<string, string>
     */
    private static function parametersOfMode(PaginationMode $mode, Pagination $pagination): array
    {
        return match ($mode) {
            PaginationMode::PAGES => ['page' => 'integer', 'per_page' => 'integer'],
            PaginationMode::MORE => $pagination->continuation === Continuation::ENDPOINT
                ? ['cursor' => 'string']
                : ['shown' => 'integer'],
            PaginationMode::NONE => [],
        };
    }
}
