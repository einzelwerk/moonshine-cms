<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\Cms\Layouts\OpenApi\MenuSlotsProcessor;
use OpenApi\Analysis;
use OpenApi\Context;
use OpenApi\Generator;
use OpenApi\Utils\Pipeline;

/**
 * The slots travel into the contract as properties and not as prose.
 */
function chromePartSlots(): array
{
    $generator = new Generator();

    // Appended to the end of the pipeline, the way l5-swagger adds its own
    // processors: the schema is assembled by then, and the processor adds no
    // references.
    $pipes = [];
    $generator->getProcessorPipeline()->walk(function (callable $pipe) use (&$pipes): void {
        $pipes[] = $pipe;
    });
    $generator->setProcessorPipeline(new Pipeline([...$pipes, new MenuSlotsProcessor()]));

    // No validation: only the schemas of the package are scanned, without the
    // `OA\Info` of the whole document — the application writes that.
    $openapi = $generator->generate([__DIR__.'/../../../src/Layouts/OpenApi'], null, false);

    $document = json_decode((string) $openapi?->toJson(), true, 512, JSON_THROW_ON_ERROR);

    return $document['components']['schemas']['ChromePart']['properties']['slots'] ?? [];
}

it('gives every chrome slot of the catalog a property of its own and nothing else', function (): void {
    config()->set('cms-layouts.menu_slots', ['aside']);
    app(MenuSlotCatalog::class)->register(MenuSlotDefinition::make('mail_legal'));

    $slots = chromePartSlots();
    $properties = array_keys($slots['properties'] ?? []);

    // The footer of a letter is no part of the chrome the frontend draws.
    expect($properties)->toBe(app(MenuSlotCatalog::class)->chromeCodes())
        ->and($properties)->toContain('aside')
        ->and($properties)->not->toContain('mail_legal')
        ->and($slots['additionalProperties'] ?? null)->toBeFalse()
        ->and($slots['properties']['footer_1']['type'] ?? null)->toBe('array');
});

it('leaves a document without the chrome schemas alone', function (): void {
    $analysis = new Analysis([], new Context());

    // No document at all is the case of an installation that generates a
    // specification of its own endpoints only: the processor has nothing to
    // narrow and must not invent it.
    (new MenuSlotsProcessor())($analysis);

    expect($analysis->openapi)->toBeNull();
});
