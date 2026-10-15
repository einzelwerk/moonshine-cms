<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

it('reports the blocks its page type demands', function (): void {
    $page = new CmsPage(['type' => 'concert', 'slug' => 'concert', 'name' => ['ru' => 'Концерт']]);

    expect($page)->toBeInstanceOf(DeclaresRequiredBlocks::class)
        ->and($page->requiredBlockCodes())->toBe(['hero', 'checkout']);
});

it('demands nothing for a type that requires nothing or is not declared here', function (string $type): void {
    expect((new CmsPage(['type' => $type, 'slug' => 'info', 'name' => []]))->requiredBlockCodes())->toBe([]);
})->with(['page', 'venue']);
