<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Blocks\TocBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\OpenApi\BlockSchemaGenerator;
use Ewk\Cms\Blocks\OpenApi\CmsSchemasProcessor;
use Ewk\Cms\Blocks\OpenApi\CommonSchemas;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\BuyAction;
use Ewk\Cms\Blocks\Tests\Fixtures\CatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\CursorFeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\FeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\MediaBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\PanelBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\SwitchableListBlock;
use Illuminate\Http\Request;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Undefined;

/**
 * The generated contract of the blocks.
 *
 * The test the contract cannot do without lives here: every registered type is
 * in the union and its single-value enum equals the code of the registry. Drop
 * that enum and narrowing by `block.code` stops working on the frontend,
 * because orval throws the discriminator away.
 */
function cmsGenerator(): BlockSchemaGenerator
{
    return new BlockSchemaGenerator(app(BlockRegistryInterface::class));
}

/**
 * @return array<string, OA\Schema>
 */
function cmsGenerated(): array
{
    $byName = [];

    foreach (cmsGenerator()->schemas() as $schema) {
        $byName[(string) $schema->schema] = $schema;
    }

    return $byName;
}

/**
 * @return array<string, OA\Schema>
 */
function cmsCommon(): array
{
    $byName = [];

    foreach (CommonSchemas::schemas() as $schema) {
        $byName[(string) $schema->schema] = $schema;
    }

    return $byName;
}

function cmsPropertyOf(OA\Schema $schema, string $name): ?OA\Property
{
    foreach ((array) $schema->properties as $property) {
        if ($property instanceof OA\Property && $property->property === $name) {
            return $property;
        }
    }

    return null;
}

/**
 * A document just complete enough to validate: the processor only ever touches
 * `components`.
 */
function cmsDocument(): OA\OpenApi
{
    $context = new Context(['generated' => true]);

    $openapi = new OA\OpenApi(['_context' => $context]);
    $openapi->openapi = '3.0.0';
    $openapi->info = new OA\Info(['_context' => $context, 'title' => 'Test', 'version' => '1.0']);
    $openapi->paths = [new OA\PathItem(['_context' => $context, 'path' => '/v1/resolve'])];

    return $openapi;
}

function cmsProcessed(OA\OpenApi $openapi): OA\OpenApi
{
    $analysis = new Analysis([], new Context(['generated' => true]));
    $analysis->openapi = $openapi;

    (new CmsSchemasProcessor())($analysis);

    return $openapi;
}

beforeEach(function (): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->register(FeedBlock::class);
    $registry->register(CatalogBlock::class);
    $registry->register(PanelBlock::class);
    $registry->register(SwitchableListBlock::class);
    $registry->register(CursorFeedBlock::class);
    $registry->register(MediaBlock::class);
});

it('describes every registered type with an envelope and a payload', function (): void {
    $schemas = cmsGenerated();

    foreach (cmsGenerator()->codes() as $code) {
        $envelope = BlockSchemaGenerator::envelopeName($code);
        $payload = BlockSchemaGenerator::payloadName($code);

        expect($schemas)->toHaveKey($envelope);
        expect($schemas)->toHaveKey($payload);
    }

    expect($schemas)->toHaveKeys(['TextBlock', 'TextBlockData', 'TocBlock', 'ColumnsBlock', 'FeedBlock', 'CatalogBlock']);
});

it('keeps the code of every variant a single-value enum equal to the registry code', function (): void {
    $schemas = cmsGenerated();

    foreach (cmsGenerator()->codes() as $code) {
        $property = cmsPropertyOf($schemas[BlockSchemaGenerator::envelopeName($code)], 'code');

        expect($property)->not->toBeNull();
        expect($property->enum)->toBe([$code]);
    }
});

it('puts every type in the union and in the mapping of the discriminator', function (): void {
    $union = cmsGenerated()[BlockSchemaGenerator::BLOCK];
    $codes = cmsGenerator()->codes();

    $refs = array_map(static fn (OA\Schema $variant): string => (string) $variant->ref, $union->oneOf);

    expect($refs)->toHaveCount(count($codes));
    expect($union->discriminator->propertyName)->toBe('code');

    foreach ($codes as $code) {
        $ref = CommonSchemas::ref(BlockSchemaGenerator::envelopeName($code));

        expect($refs)->toContain($ref);
        expect($union->discriminator->mapping[$code] ?? null)->toBe($ref);
    }
});

it('leaves the insertion of a shared block out of the union', function (): void {
    expect(app(BlockRegistryInterface::class)->has(SharedInsertionBlock::code()))->toBeTrue();
    expect(cmsGenerator()->codes())->not->toContain(SharedInsertionBlock::code());
    expect(cmsGenerated())->not->toHaveKey('SharedBlock');
});

it('derives the payload of a static type from its declared fields', function (): void {
    $data = cmsGenerated()['TextBlockData'];

    expect($data->type)->toBe('object');
    expect($data->required)->toBe(['title', 'body']);
    expect(cmsPropertyOf($data, 'body')->type)->toBe('string');
    // The dictionary of languages never leaves the backend: the resolved string
    // does.
    expect(Undefined::isDefault(cmsPropertyOf($data, 'body')->nullable))->toBeTrue();
});

it('adds the pagination and the filters to the payload of a dynamic type', function (): void {
    $data = cmsGenerated()['CatalogBlockData'];

    expect(cmsPropertyOf($data, 'pagination')->ref)->toBe(CommonSchemas::ref(CommonSchemas::PAGINATION_PAGES));
    expect(cmsPropertyOf($data, 'filters')->ref)->toBe(CommonSchemas::ref(CommonSchemas::FILTERS));
    expect($data->required)->toContain('pagination');
    // A block draws the panel only where there is something to narrow.
    expect($data->required)->not->toContain('filters');
});

it('carries the declared parameters of a dynamic type in x-cms-params', function (): void {
    $parameters = cmsGenerated()['CatalogBlockData']->x['cms-params'];
    $names = array_column($parameters, 'name');

    expect($names)->toContain('city');
    // The parameters of the pagination itself are added by the mode and are
    // never listed by the schema.
    expect($names)->toContain('page');

    $city = $parameters[array_search('city', $names, true)];

    expect($city['type'])->toBe('string');
    expect($city['filter'])->toBeTrue();
});

it('leaves x-cms-params off a static type', function (): void {
    expect(Undefined::isDefault(cmsGenerated()['TextBlockData']->x))->toBeTrue();
});

it('registers the components in the document and validates', function (): void {
    $openapi = cmsProcessed(cmsDocument());

    $names = [];

    foreach ($openapi->components->schemas as $schema) {
        $names[] = (string) $schema->schema;
    }

    expect($names)->toContain(CommonSchemas::MEDIA, CommonSchemas::LINK, CommonSchemas::PAGINATION, CommonSchemas::FILTERS);
    expect($names)->toContain(BlockSchemaGenerator::BLOCK, BlockSchemaGenerator::CHILDREN, 'TextBlock', 'TextBlockData');
    // The refusals of the CMS are declared once, in this package.
    expect($names)->toContain(CommonSchemas::ERROR, CommonSchemas::VALIDATION_ERROR);
    expect(array_count_values($names)[CommonSchemas::ERROR])->toBe(1);

    expect($openapi->validate())->toBeTrue();
});

it('declares the language header and makes it mandatory on more than one language', function (): void {
    $openapi = cmsProcessed(cmsDocument());

    $parameter = null;

    foreach ($openapi->components->parameters as $candidate) {
        if ($candidate->parameter === CommonSchemas::ACCEPT_LANGUAGE) {
            $parameter = $candidate;
        }
    }

    expect($parameter)->not->toBeNull();
    expect($parameter->name)->toBe('Accept-Language');
    expect($parameter->in)->toBe('header');
    // The catalog of the package ships two languages.
    expect($parameter->required)->toBeTrue();
});

it('makes the language header optional on a single language', function (): void {
    config()->set('cms-media.locales', ['ru']);

    $openapi = cmsProcessed(cmsDocument());

    foreach ($openapi->components->parameters as $candidate) {
        if ($candidate->parameter === CommonSchemas::ACCEPT_LANGUAGE) {
            expect($candidate->required)->toBeFalse();
        }
    }
});

it('skips a component the document already defines identically', function (): void {
    $openapi = cmsDocument();
    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
    $openapi->components->schemas = [];

    cmsProcessed($openapi);

    $before = count($openapi->components->schemas);

    cmsProcessed($openapi);

    expect($openapi->components->schemas)->toHaveCount($before);
});

it('refuses a component name already taken by another definition', function (): void {
    $openapi = cmsDocument();
    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
    $openapi->components->schemas = [
        new OA\Schema(['_context' => $openapi->_context, 'schema' => CommonSchemas::MEDIA, 'type' => 'string']),
    ];

    expect(fn (): OA\OpenApi => cmsProcessed($openapi))
        ->toThrow(LogicException::class, CommonSchemas::MEDIA);
});

it('describes the children of a container as a recursive map of slots', function (): void {
    $children = cmsGenerated()[BlockSchemaGenerator::CHILDREN];

    expect($children->type)->toBe('object');
    expect($children->additionalProperties->type)->toBe('array');
    expect((string) $children->additionalProperties->items->ref)
        ->toBe(CommonSchemas::ref(BlockSchemaGenerator::BLOCK));

    expect(cmsPropertyOf(cmsGenerated()[BlockSchemaGenerator::envelopeName(ColumnsBlock::code())], 'children')->ref)
        ->toBe(CommonSchemas::ref(BlockSchemaGenerator::CHILDREN));

    expect(cmsPropertyOf(cmsGenerated()[BlockSchemaGenerator::envelopeName(TocBlock::code())], 'children')->ref)
        ->toBe(CommonSchemas::ref(BlockSchemaGenerator::CHILDREN));

    // `null` in the enumeration and not only `nullable`: a block without a
    // variant answers null, and in OpenAPI 3.0 the enumeration has to list it.
    expect(cmsPropertyOf(cmsGenerated()[BlockSchemaGenerator::envelopeName(TextBlock::code())], 'variant')->enum)
        ->toBe(['default', 'narrow', null]);
});

it('never emits an empty required list, which is not valid OpenAPI', function (): void {
    foreach (cmsGenerated() as $name => $schema) {
        if (! Undefined::isDefault($schema->required)) {
            expect($schema->required)->not->toBe([], "Пустой required у компонента [{$name}]");
        }
    }
});

it('lets a resource describe a payload the declaration cannot show', function (): void {
    // `toc` adds to its own heading the anchors of the whole page, and
    // `columns` adds the shares of its slots to the stored values.
    $toc = cmsGenerated()['TocBlockData'];

    expect($toc->required)->toBe(['title', 'items']);
    expect(cmsPropertyOf($toc, 'items')->type)->toBe('array');

    expect(cmsGenerated()['ColumnsBlockData']->required)->toBe(['widths', 'collapse_on_mobile']);
});

it('describes a media field of the core as the stored reference, not a hydrated picture', function (): void {
    // `MediaField::toPayload()` of the core answers the reference `{id,
    // metadata}`: promising `CmsMedia` on its behalf would be untrue. A
    // hydrating resource describes its payload itself, see
    // DescribesPayloadSchema.
    $image = cmsPropertyOf(cmsGenerated()['MediaCardBlockData'], 'image');

    expect($image->allOf[0]->ref)->toBe(CommonSchemas::ref(CommonSchemas::MEDIA_REF))
        ->and($image->type)->toBe('object')
        ->and($image->nullable)->toBeTrue();

    $reference = cmsCommon()[CommonSchemas::MEDIA_REF];
    $declared = array_map(
        static fn (OA\Property $property): string => (string) $property->property,
        $reference->properties,
    );

    // The live payload of a block with this schema satisfies it: the keys are
    // declared, the required ones are there.
    $owner = BlockOwner::query()->create();
    $owner->blocks()->create([
        'type' => MediaBlock::code(),
        'name' => 'Карточка',
        'content' => ['image' => ['id' => '9f3c1a2b-4e55-4c1e-9a3f-0b71d2c35e10', 'metadata' => ['alt' => ['ru' => 'Афиша']]]],
    ]);

    $payload = app(BlockRenderer::class)->render($owner, Request::create('/'))[0]['data']['image'];

    expect(array_diff(array_keys($payload), $declared))->toBe([])
        ->and(array_diff($reference->required, array_keys($payload)))->toBe([]);
});

it('narrows the pagination of a payload to the modes the schema allows', function (): void {
    $switchable = cmsPropertyOf(cmsGenerated()['SwitchableBlockData'], 'pagination');
    $refs = array_map(static fn (OA\Schema $variant): string => (string) $variant->ref, $switchable->oneOf);

    // The schema allows only `none` and `more`, so numbered pages must not be
    // in the union.
    expect($refs)->toBe([
        CommonSchemas::ref(CommonSchemas::PAGINATION_NONE),
        CommonSchemas::ref(CommonSchemas::PAGINATION_MORE),
    ]);
    expect($switchable->discriminator->propertyName)->toBe('mode');

    // A fixed mode is a shape of its own, one branch fewer.
    expect(cmsPropertyOf(cmsGenerated()['CatalogBlockData'], 'pagination')->ref)
        ->toBe(CommonSchemas::ref(CommonSchemas::PAGINATION_PAGES));
});

it('lets the mode add only the parameters of its own transport', function (): void {
    $names = static fn (string $component): array => array_column(
        cmsGenerated()[$component]->x['cms-params'],
        'name',
    );

    // Numbered pages add `page` and `per_page` whatever `editable` says: the
    // resolution takes the portion from the request and only clamps it.
    expect($names('CatalogBlockData'))->toContain('page', 'per_page');

    // The accumulator in the address of the page is `shown`, the cursor
    // transport is `cursor`, and never both at once.
    expect($names('FeedBlockData'))->toContain('shown')
        ->and($names('FeedBlockData'))->not->toContain('cursor');
    expect($names('CursorFeedBlockData'))->toContain('cursor')
        ->and($names('CursorFeedBlockData'))->not->toContain('shown');
});

it('describes a date parameter with the OpenAPI format and keeps the php one apart', function (): void {
    $parameters = cmsGenerated()['FeedBlockData']->x['cms-params'];
    $date = $parameters[array_search('date', array_column($parameters, 'name'), true)];

    expect($date['format'])->toBe('date')
        ->and($date['date_format'])->toBe('Y-m-d');
});

it('fills in an open schema only where the document declares none', function (string $name): void {
    $openapi = cmsProcessed(cmsDocument());

    $found = null;

    foreach ($openapi->components->schemas as $schema) {
        if ((string) $schema->schema === $name) {
            $found = $schema;
        }
    }

    expect($found)->not->toBeNull()
        ->and($found->type)->toBe('object')
        ->and($found->additionalProperties)->toBeTrue();
})->with([CommonSchemas::RESOLVE_EXTENSION]);

it('leaves a CmsResolveExtension of the application alone instead of refusing it', function (): void {
    $openapi = cmsDocument();
    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
    $declared = new OA\Schema([
        '_context' => $openapi->_context,
        'schema' => CommonSchemas::RESOLVE_EXTENSION,
        'required' => ['integration'],
    ]);
    $openapi->components->schemas = [$declared];

    cmsProcessed($openapi);

    $found = array_values(array_filter(
        $openapi->components->schemas,
        static fn (OA\Schema $schema): bool => (string) $schema->schema === CommonSchemas::RESOLVE_EXTENSION,
    ));

    expect($found)->toHaveCount(1)
        ->and($found[0])->toBe($declared);
});

it('describes the action of a link as the keys the resolver hands out', function (): void {
    app(ActionRegistry::class)->register(new BuyAction());

    $action = cmsPropertyOf(cmsCommon()[CommonSchemas::LINK], 'action');
    $resolved = app(LinkResolverRegistry::class)
        ->resolve(new Link(LinkKind::ACTION, ['action' => 'buy', 'params' => ['event' => 'icegergert']]), 'ru')
        ->toArray()['action'];

    expect($action->type)->toBe('object')
        ->and($action->nullable)->toBeTrue()
        ->and($action->required)->toBe(['name', 'params'])
        ->and(cmsPropertyOf($action, 'name')->type)->toBe('string')
        ->and(cmsPropertyOf($action, 'params')->type)->toBe('object')
        ->and(array_keys((array) $resolved))->toBe($action->required);
});
