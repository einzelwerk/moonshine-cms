<?php

declare(strict_types=1);

use Ewk\Cms\Pages\OpenApi\SubjectSchemaGenerator;
use Ewk\Cms\Pages\OpenApi\SubjectSchemasProcessor;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\SubjectPresentation;
use Illuminate\Http\Request;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Context;

function subjectDocument(): OA\OpenApi
{
    $context = new Context(['generated' => true]);

    $openapi = new OA\OpenApi(['_context' => $context]);
    $openapi->openapi = '3.0.0';
    $openapi->info = new OA\Info(['_context' => $context, 'title' => 'Test', 'version' => '1.0']);
    $openapi->paths = [new OA\PathItem(['_context' => $context, 'path' => '/v1/resolve'])];

    return $openapi;
}

/**
 * @return array<string, array<string, mixed>>
 */
function subjectProcessed(OA\OpenApi $openapi): array
{
    $analysis = new Analysis([], new Context(['generated' => true]));
    $analysis->openapi = $openapi;

    (new SubjectSchemasProcessor())($analysis);

    $byName = [];

    foreach ($openapi->components->schemas as $schema) {
        $serialized = json_decode((string) json_encode($schema->jsonSerialize()), true);
        $byName[(string) $schema->schema] = is_array($serialized) ? $serialized : [];
    }

    return $byName;
}

function subjectTypes(): PageTypeRegistry
{
    $registry = app(PageTypeRegistry::class);
    $registry->register(TestPageTypes::page());
    $registry->register(TestPageTypes::gig());
    $registry->register(TestPageTypes::recital());

    return $registry;
}

it('makes BlockSubject a union of the template types discriminated by type', function (): void {
    subjectTypes();

    $schemas = subjectProcessed(subjectDocument());
    $subject = $schemas[SubjectSchemaGenerator::SUBJECT];

    expect($subject['oneOf'])->toBe([
        ['$ref' => '#/components/schemas/BlockSubjectGig'],
        ['$ref' => '#/components/schemas/BlockSubjectRecital'],
    ])
        ->and($subject['discriminator'])->toBe([
            'propertyName' => 'type',
            'mapping' => [
                'gig' => '#/components/schemas/BlockSubjectGig',
                'recital' => '#/components/schemas/BlockSubjectRecital',
            ],
        ]);

    expect($schemas['BlockSubjectGig']['required'])->toBe(['type', 'data'])
        ->and($schemas['BlockSubjectGig']['properties']['type']['enum'])->toBe(['gig'])
        ->and($schemas['BlockSubjectGig']['properties']['data'])->toBe([
            'description' => 'Данные сущности',
            'allOf' => [['$ref' => '#/components/schemas/ConcertResource']],
        ])
        ->and($schemas['BlockSubjectRecital']['properties']['type']['enum'])->toBe(['recital'])
        ->and($schemas['BlockSubjectRecital']['properties']['data']['type'])->toBe('object')
        ->and($schemas['BlockSubjectRecital']['properties']['data']['additionalProperties'])->toBeFalse();
});

it('lists the described presenters in CmsSubjectData and an empty object for a type without one', function (): void {
    subjectTypes();

    $data = subjectProcessed(subjectDocument())[SubjectSchemaGenerator::SUBJECT_DATA];

    expect($data['anyOf'])->toBe([
        ['$ref' => '#/components/schemas/ConcertResource'],
        ['type' => 'object', 'additionalProperties' => false],
    ]);
});

it('falls back to open schemas when no type has a subject', function (): void {
    app(PageTypeRegistry::class)->register(TestPageTypes::page());

    $schemas = subjectProcessed(subjectDocument());

    expect($schemas[SubjectSchemaGenerator::SUBJECT]['properties']['data'])
        ->toBe([
            'description' => 'Данные сущности',
            'allOf' => [['$ref' => '#/components/schemas/CmsSubjectData']],
        ])
        ->and($schemas[SubjectSchemaGenerator::SUBJECT_DATA]['type'])->toBe('object')
        ->and($schemas[SubjectSchemaGenerator::SUBJECT_DATA]['additionalProperties'])->toBeTrue();
});

it('leaves a CmsSubjectData of the application alone', function (): void {
    subjectTypes();

    $openapi = subjectDocument();
    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
    $openapi->components->schemas = [
        new OA\Schema(['_context' => $openapi->_context, 'schema' => SubjectSchemaGenerator::SUBJECT_DATA, 'type' => 'string']),
    ];

    expect(subjectProcessed($openapi)[SubjectSchemaGenerator::SUBJECT_DATA])
        ->toBe(['schema' => SubjectSchemaGenerator::SUBJECT_DATA, 'type' => 'string']);
});

it('refuses a BlockSubject the application declares with another shape', function (): void {
    subjectTypes();

    $openapi = subjectDocument();
    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
    $openapi->components->schemas = [
        new OA\Schema(['_context' => $openapi->_context, 'schema' => SubjectSchemaGenerator::SUBJECT, 'type' => 'object']),
    ];

    expect(fn (): array => subjectProcessed($openapi))
        ->toThrow(LogicException::class, SubjectSchemaGenerator::SUBJECT);
});

it('hands a block the subject of a type without a presenter with an empty object', function (): void {
    app(PageTypeRegistry::class)->register(TestPageTypes::recital());

    $payload = app(SubjectPresentation::class)->payload(Make::concert('jazz', 'solo'), Request::create('/'));

    expect(json_encode($payload))->toBe('{"type":"recital","data":{}}');
});
