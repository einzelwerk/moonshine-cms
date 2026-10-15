<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\BlockRegistry;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Events\UnknownBlockTypeEncountered;
use Ewk\Cms\Blocks\Params\BlockParameters;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Tests\Fixtures\CatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\CountedCatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\CountingCityOptions;
use Ewk\Cms\Blocks\Tests\Fixtures\CursorFeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\FeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\SwitchableListBlock;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $registry = app(BlockRegistryInterface::class);

    $registry->register(CatalogBlock::class);
    $registry->register(FeedBlock::class);
    $registry->register(CursorFeedBlock::class);
    $registry->register(SwitchableListBlock::class);
    $registry->register(CountedCatalogBlock::class);

    CountingCityOptions::reset();
});

function dynamicRenderer(): BlockRenderer
{
    return app(BlockRenderer::class);
}

/**
 * @param  array<string, mixed>  $query
 */
function pageQuery(array $query): BlockParameters
{
    return BlockParameters::fromPageQuery($query);
}

it('renders the numbered payload of a list out of the parameters of the page', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([
        'b' => ['afisha' => ['page' => '2', 'city' => 'berlin']],
    ]));

    expect($rendered[0]['data']['pagination'])->toBe([
        'mode' => 'pages',
        'per_page' => 5,
        'current_page' => 2,
        'last_page' => 9,
        'total' => 42,
        'has_more' => true,
        'links' => [
            'self' => '?b[afisha][city]=berlin&b[afisha][page]=2',
            'next' => '?b[afisha][city]=berlin&b[afisha][page]=3',
            'prev' => '?b[afisha][city]=berlin',
        ],
    ])
        ->and($rendered[0]['data']['filters']['fields']['city']['value'])->toBe('berlin');
});

it('keeps the parameters of one list out of the address of the other', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);
    Make::block($owner, ['type' => 'feed', 'anchor' => 'lenta', 'sort_order' => 1]);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([
        'b' => ['afisha' => ['page' => '2'], 'lenta' => ['shown' => '24']],
    ]));

    expect($rendered[0]['data']['pagination']['links']['next'])
        ->toBe('?b[afisha][page]=3&b[lenta][shown]=24')
        ->and($rendered[1]['data']['pagination']['next'])
        ->toBe('?b[afisha][page]=2&b[lenta][shown]=36');
});

it('gives a block that is not dynamic no parameters at all', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'text', 'content' => ['body' => ['ru' => '<p>Текст</p>']]]);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([]));

    expect($rendered[0]['data'])->toBe(['title' => '', 'body' => '<p>Текст</p>']);
});

it('renders a list without any parameters in its first state', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $rendered = dynamicRenderer()->render($owner, request());

    expect($rendered[0]['data']['pagination']['current_page'])->toBe(1)
        ->and($rendered[0]['data']['pagination']['links']['self'])->toBe('?');
});

it('counts the pages by the portion of the request and keeps it in every link', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([
        'b' => ['afisha' => ['per_page' => '20']],
    ]));

    expect($rendered[0]['data']['pagination']['per_page'])->toBe(20)
        ->and($rendered[0]['data']['pagination']['last_page'])->toBe(3)
        ->and($rendered[0]['data']['pagination']['links']['self'])->toBe('?b[afisha][per_page]=20')
        ->and($rendered[0]['data']['pagination']['links']['next'])
        ->toBe('?b[afisha][page]=2&b[afisha][per_page]=20');
});

it('reports a type that left the registry and renders nothing for it', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);

    // The type is gone from the registry while the rows and the snapshot keep
    // it: stale data reports itself instead of failing the response.
    app()->instance(BlockRegistryInterface::class, new BlockRegistry(app()));
    Event::fake([UnknownBlockTypeEncountered::class]);

    $envelope = dynamicRenderer()->renderPublishedBlock(
        $owner,
        $block->uuid,
        request(),
        BlockParameters::forSingle('afisha', []),
    );

    expect($envelope)->toBeNull();

    Event::assertDispatched(
        UnknownBlockTypeEncountered::class,
        static fn (UnknownBlockTypeEncountered $event): bool => $event->type === 'catalog'
            && $event->uuid === $block->uuid,
    );
});

it('rejects an impossible parameter of a page with a validation error', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    expect(fn () => dynamicRenderer()->render($owner, request(), pageQuery([
        'b' => ['afisha' => ['city' => 'paris']],
    ])))->toThrow(ValidationException::class);
});

it('renders one published block by its uuid', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);

    $envelope = dynamicRenderer()->renderPublishedBlock(
        $owner,
        $block->uuid,
        request(),
        BlockParameters::forSingle('afisha', ['page' => '2']),
    );

    expect($envelope['uuid'])->toBe($block->uuid)
        ->and($envelope['code'])->toBe('catalog')
        ->and($envelope['data']['pagination']['current_page'])->toBe(2);
});

it('gives nothing for a uuid that is not in the snapshot of this owner', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $other = Make::owner();
    $foreign = Make::block($other, ['type' => 'catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);
    app(PublishOwner::class)->handle($other);

    $renderer = dynamicRenderer();
    $parameters = BlockParameters::forSingle('afisha', []);

    expect($renderer->renderPublishedBlock($owner, $foreign->uuid, request(), $parameters))->toBeNull()
        ->and($renderer->renderPublishedBlock($owner, $block->uuid, request(), $parameters))->not->toBeNull();
});

it('gives nothing for a block that is not dynamic and for one not published yet', function (): void {
    $owner = Make::owner();
    $text = Make::block($owner, ['type' => 'text', 'content' => ['body' => ['ru' => '<p>Текст</p>']]]);
    $catalog = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha', 'sort_order' => 1]);

    $renderer = dynamicRenderer();
    $parameters = BlockParameters::forSingle('afisha', []);

    expect($renderer->renderPublishedBlock($owner, $catalog->uuid, request(), $parameters))->toBeNull();

    app(PublishOwner::class)->handle($owner);

    expect($renderer->renderPublishedBlock($owner, $text->uuid, request(), $parameters))->toBeNull()
        ->and($renderer->renderPublishedBlock($owner, $catalog->uuid, request(), $parameters))->not->toBeNull();
});

it('gives nothing for a switched off block and for a switched off owner', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);

    $renderer = dynamicRenderer();
    $parameters = BlockParameters::forSingle('afisha', []);

    $block->is_active = false;
    $block->save();
    app(PublishOwner::class)->handle($owner);

    expect($renderer->renderPublishedBlock($owner, $block->uuid, request(), $parameters))->toBeNull();

    $block->is_active = true;
    $block->save();
    app(PublishOwner::class)->handle($owner);
    $owner->is_active = false;
    $owner->save();

    expect($renderer->renderPublishedBlock($owner, $block->uuid, request(), $parameters))->toBeNull();
});

it('reads the parameters of the same source the render reads', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);

    Make::block($owner, ['type' => 'feed', 'anchor' => 'lenta', 'sort_order' => 1]);

    $renderer = dynamicRenderer();
    $raw = pageQuery(['b' => ['afisha' => ['page' => '2'], 'lenta' => ['shown' => '24']]]);

    expect($renderer->parametersOf($owner, $raw)->anchors())->toBe(['afisha'])
        ->and($renderer->parametersOf($owner, $raw, published: false)->anchors())
        ->toBe(['afisha', 'lenta']);
});

it('gives the parameters of a list nested inside a container', function (): void {
    $owner = Make::owner();
    $columns = Make::block($owner, ['type' => 'columns', 'variant' => '2']);
    Make::block($owner, [
        'type' => 'catalog',
        'anchor' => 'afisha',
        'parent_id' => $columns->getKey(),
        'slot' => 'col_1',
    ]);

    $resolved = dynamicRenderer()->parametersOf(
        $owner,
        pageQuery(['b' => ['afisha' => ['page' => '4']]]),
        published: false,
    );

    expect($resolved->for('afisha')?->page)->toBe(4);
});

it('continues a cursor list through the endpoint of the block', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'cursor_feed', 'anchor' => 'kursor']);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([]));

    expect($rendered[0]['data']['pagination'])->toBe([
        'mode' => 'more',
        'continuation' => 'endpoint',
        'per_page' => 10,
        'has_more' => true,
        'next' => '/api/v1/blocks/'.$block->uuid.'?path=%2F&cursor=ZW5k',
    ]);
});

it('renders the growing payload of a block whose editor turned the continuation on', function (): void {
    $owner = Make::owner();
    Make::block($owner, [
        'type' => 'switchable',
        'anchor' => 'podborka',
        'content' => ['pagination_mode' => 'more'],
    ]);
    Make::block($owner, ['type' => 'switchable', 'anchor' => 'ryad', 'sort_order' => 1]);

    $rendered = dynamicRenderer()->render($owner, request(), pageQuery([
        'b' => ['podborka' => ['shown' => '18']],
    ]));

    expect($rendered[0]['data']['pagination'])->toBe([
        'mode' => 'more',
        'continuation' => 'url',
        'per_page' => 6,
        'shown' => 18,
        'total' => 42,
        'has_more' => true,
        'next' => '?b[podborka][shown]=24',
        'catalog' => ['href' => '/events'],
    ])
        ->and($rendered[1]['data']['pagination'])->toBe(['mode' => 'none']);
});

it('hands the published blocks and their parameters out of one traversal', function (): void {
    $owner = Make::owner();
    $block = Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $version = app(PublishOwner::class)->handle($owner);

    $document = dynamicRenderer()->renderPublishedDocument(
        $owner,
        request(),
        pageQuery(['b' => ['afisha' => ['page' => '2', 'city' => 'berlin']]]),
    );

    expect($document?->blocks)->toHaveCount(1)
        ->and($document?->blocks[0]['uuid'])->toBe($block->uuid)
        ->and($document?->parameters->for('afisha')?->page)->toBe(2)
        ->and($document?->parameters->for('afisha')?->get('city'))->toBe('berlin')
        ->and($document?->version->getKey())->toBe($version->getKey())
        ->and($document?->version->published_at)->not->toBeNull();
});

it('gives no document for an owner without a snapshot and for a switched off one', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'catalog', 'anchor' => 'afisha']);

    $renderer = dynamicRenderer();
    $raw = pageQuery([]);

    expect($renderer->renderPublishedDocument($owner, request(), $raw))->toBeNull();

    app(PublishOwner::class)->handle($owner);
    $owner->is_active = false;
    $owner->save();

    expect($renderer->renderPublishedDocument($owner, request(), $raw))->toBeNull();
});

it('resolves the parameters of a page once, where the two calls resolve them twice', function (): void {
    $owner = Make::owner();
    Make::block($owner, ['type' => 'counted_catalog', 'anchor' => 'afisha']);

    app(PublishOwner::class)->handle($owner);

    $renderer = dynamicRenderer();
    $raw = pageQuery(['b' => ['afisha' => ['city' => 'berlin']]]);

    CountingCityOptions::reset();
    $document = $renderer->renderPublishedDocument($owner, request(), $raw);
    $once = CountingCityOptions::$calls;

    CountingCityOptions::reset();
    $blocks = $renderer->renderPublished($owner, request(), $raw);
    $parameters = $renderer->parametersOf($owner, $raw);
    $twice = CountingCityOptions::$calls;

    expect($once)->toBeGreaterThan(0)
        ->and($twice)->toBe($once * 2)
        // toEqual and not toBe: the envelope holds an empty map of slots as an
        // object, and two renders give two instances of the same value.
        ->and($document?->blocks)->toEqual($blocks)
        ->and($document?->parameters->toQuery())->toBe($parameters->toQuery());
});
