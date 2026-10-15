<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\SlotlessContainerBlock;
use Illuminate\Http\Request;

/**
 * `children` travels to the frontend as a map of slots, so an empty set is an
 * object and not an empty array. In PHP the two differ only once encoded, and
 * only the raw JSON tells them apart.
 */
it('encodes the children of a container whose variant declares no slots as an empty map', function (): void {
    app(BlockRegistryInterface::class)->register(SlotlessContainerBlock::class);

    $owner = BlockOwner::query()->create();
    $owner->blocks()->create([
        'type' => SlotlessContainerBlock::code(),
        'name' => 'Контейнер',
        'content' => [],
    ]);

    $rendered = app(BlockRenderer::class)->render($owner, Request::create('/'));

    expect($rendered[0]['children'])->toBeInstanceOf(stdClass::class)
        ->and(json_encode($rendered, JSON_UNESCAPED_UNICODE))->toContain('"children":{}');
});
