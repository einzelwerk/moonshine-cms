<?php

declare(strict_types=1);

use Ewk\Cms\Pages\OpenApi\PageSecurityProcessor;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\Utils\Pipeline;

/**
 * @return array<string, mixed>
 */
function publicContract(bool $withSecurityProcessor = false): array
{
    $src = __DIR__.'/../../../../src';
    $generator = new Generator();

    if ($withSecurityProcessor) {
        $pipes = [];
        $generator->getProcessorPipeline()->walk(function (callable $pipe) use (&$pipes): void {
            $pipes[] = $pipe;
        });
        $generator->setProcessorPipeline(new Pipeline([
            ...$pipes,
            static function (Analysis $analysis): void {
                $openapi = $analysis->openapi;

                if ($openapi instanceof OA\OpenApi) {
                    $openapi->components = new OA\Components(['_context' => $openapi->_context]);
                    $openapi->components->securitySchemes = [new OA\SecurityScheme([
                        '_context' => $openapi->_context,
                        'securityScheme' => 'clientUuid',
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Client-Uuid',
                    ])];
                }
            },
            new PageSecurityProcessor(),
        ]));
    }

    $openapi = $generator->generate([
        $src.'/Pages/Http/Controllers',
        $src.'/Pages/OpenApi/Schemas',
        $src.'/Layouts/Http/Controllers',
        $src.'/Seo/Http/Controllers',
    ], null, false);

    $document = json_decode((string) $openapi?->toJson(), true, 512, JSON_THROW_ON_ERROR);

    return is_array($document) ? $document : [];
}

/**
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function securityByOperation(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $item) {
        foreach ($item as $operation) {
            $operations[$operation['operationId']] = $operation['security'] ?? null;
        }
    }

    ksort($operations);

    return $operations;
}

it('declares every operation of the package public with an empty security list', function (): void {
    expect(securityByOperation(publicContract()))->toBe([
        'CmsChromeV1' => [],
        'CmsMenuV1' => [],
        'CmsResolveV1' => [],
        'CmsShowBlockV1' => [],
        'CmsSitemapFileV1' => [],
        'CmsSitemapIndexV1' => [],
    ]);
});

it('declares the subject of the resolver as a nullable reference to one component', function (): void {
    $schemas = publicContract()['components']['schemas'];

    expect($schemas['CmsResolveResponse']['allOf'][1]['properties']['subject'])->toBe([
        'description' => 'Сущность шаблонного адреса; null на обычной странице',
        'type' => 'object',
        'nullable' => true,
        'allOf' => [['$ref' => '#/components/schemas/CmsResolveSubject']],
    ])
        ->and($schemas['CmsResolveSubject']['allOf'][0])->toBe(['$ref' => '#/components/schemas/BlockSubject'])
        ->and($schemas['CmsResolveSubject']['allOf'][1]['required'])->toBe(['slug'])
        ->and(array_keys($schemas['CmsResolveSubject']['allOf'][1]['properties']))->toBe(['slug'])
        ->and($schemas['CmsResolveSubject'])->not->toHaveKey('nullable');
});

it('writes the configured security on the operations that render blocks only', function (): void {
    config()->set(PageSecurityProcessor::CONFIG, [[], ['clientUuid' => []]]);

    $optional = [[], ['clientUuid' => []]];

    expect(securityByOperation(publicContract(withSecurityProcessor: true)))->toBe([
        'CmsChromeV1' => [],
        'CmsMenuV1' => [],
        'CmsResolveV1' => $optional,
        'CmsShowBlockV1' => $optional,
        'CmsSitemapFileV1' => [],
        'CmsSitemapIndexV1' => [],
    ]);
});

it('keeps the operations that render blocks public by default', function (): void {
    expect(securityByOperation(publicContract(withSecurityProcessor: true)))
        ->toHaveKey('CmsResolveV1', [])
        ->toHaveKey('CmsShowBlockV1', []);
});

it('refuses a security scheme the document does not declare', function (): void {
    config()->set(PageSecurityProcessor::CONFIG, [[], ['bearerAuth' => []]]);

    expect(fn (): array => publicContract(withSecurityProcessor: true))
        ->toThrow(LogicException::class, 'bearerAuth');
});
