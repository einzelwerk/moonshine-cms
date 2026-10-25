<?php

declare(strict_types=1);

use OpenApi\Generator;

/**
 * @return array<string, mixed>
 */
function publicContract(): array
{
    $src = __DIR__.'/../../../../src';

    $openapi = (new Generator())->generate([
        $src.'/Pages/Http/Controllers',
        $src.'/Pages/OpenApi/Schemas',
        $src.'/Layouts/Http/Controllers',
        $src.'/Seo/Http/Controllers',
    ], null, false);

    $document = json_decode((string) $openapi?->toJson(), true, 512, JSON_THROW_ON_ERROR);

    return is_array($document) ? $document : [];
}

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
