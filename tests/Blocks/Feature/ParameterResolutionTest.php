<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Params\BlockParameters;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\ParameterResolution;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Blocks\Tests\Fixtures\CatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\CountedCatalogBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\CountingCityOptions;
use Ewk\Cms\Blocks\Tests\Fixtures\CursorFeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\EmptySetBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\FeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Ewk\Cms\Blocks\Tests\Fixtures\OpenFeedBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\PanelBlock;
use Ewk\Cms\Blocks\Tests\Fixtures\SwitchableListBlock;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $registry = app(BlockRegistryInterface::class);

    $registry->register(CatalogBlock::class);
    $registry->register(FeedBlock::class);
    $registry->register(CursorFeedBlock::class);
    $registry->register(PanelBlock::class);
    $registry->register(OpenFeedBlock::class);
    $registry->register(SwitchableListBlock::class);
    $registry->register(EmptySetBlock::class);
});

/**
 * @param  list<CmsBlock>  $blocks
 * @param  array<string, mixed>  $query
 */
function resolvePage(array $blocks, array $query): ResolvedParameters
{
    return app(ParameterResolution::class)->resolve($blocks, BlockParameters::fromPageQuery($query));
}

/**
 * The keys of the refusal, or an empty list when nothing was refused.
 *
 * @return list<string>
 */
function refusalKeys(callable $call): array
{
    try {
        $call();
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

/**
 * A resolution with limits of its own, to see the config side of an effective
 * limit: the package config may sit below the schema of a block.
 */
function limitedResolution(int $maxPerPage, int $maxShown): ParameterResolution
{
    return new ParameterResolution(
        app(BlockRegistryInterface::class),
        app(ValidationFactory::class),
        10,
        $maxPerPage,
        $maxShown,
    );
}

it('gives two lists of one page their own parameters', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);
    $schedule = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'raspisanie', 'sort_order' => 1]);

    $resolved = resolvePage([$catalog, $schedule], [
        'b' => [
            'afisha' => ['page' => '3', 'city' => 'berlin'],
            'raspisanie' => ['page' => '2', 'city' => 'koeln'],
        ],
    ]);

    expect($resolved->for('afisha')?->page)->toBe(3)
        ->and($resolved->for('afisha')?->get('city'))->toBe('berlin')
        ->and($resolved->for('raspisanie')?->page)->toBe(2)
        ->and($resolved->for('raspisanie')?->get('city'))->toBe('koeln');
});

it('drops a whole set sitting under an anchor the page does not carry', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    $resolved = resolvePage([$catalog], [
        'b' => ['afisha' => ['page' => '2'], 'staroe-imya' => ['page' => '5']],
    ]);

    expect($resolved->anchors())->toBe(['afisha'])
        ->and($resolved->for('staroe-imya'))->toBeNull()
        ->and($resolved->for('afisha')?->page)->toBe(2);
});

it('never lets an undeclared parameter reach the values', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    $resolved = resolvePage([$catalog], [
        'b' => ['afisha' => ['utm_source' => 'newsletter', 'city' => 'berlin']],
    ]);

    expect(array_keys($resolved->for('afisha')?->values ?? []))->toBe(['city', 'month'])
        ->and($resolved->for('afisha')?->get('utm_source'))->toBeNull();
});

it('substitutes the declared default of a parameter explicitly', function (): void {
    $panel = Make::block(attributes: ['type' => 'panel', 'anchor' => 'panel']);

    $resolved = resolvePage([$panel], []);

    expect($resolved->for('panel')?->values)->toBe(['city' => null, 'radius' => 10]);
});

it('names the error by the anchor for a page and by the parameter for one block', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);
    $resolution = app(ParameterResolution::class);

    $page = fn () => $resolution->resolve([$catalog], BlockParameters::fromPageQuery([
        'b' => ['afisha' => ['city' => 'paris']],
    ]));
    $single = fn () => $resolution->resolve([$catalog], BlockParameters::forSingle('afisha', ['city' => 'paris']));

    expect($page)->toThrow(ValidationException::class)
        ->and($single)->toThrow(ValidationException::class)
        ->and(refusalKeys($page))->toBe(['b.afisha.city'])
        ->and(refusalKeys($single))->toBe(['city'])
        // A refusal of another parameter is named the same way; the keys prove the refusal itself.
        ->and(refusalKeys(fn () => resolvePage([$catalog], ['b' => ['afisha' => ['month' => '2026-13']]])))
        ->toBe(['b.afisha.month']);
});

it('accepts a value that only needs its spaces trimmed', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    expect(resolvePage([$catalog], ['b' => ['afisha' => ['month' => '  2026-01  ']]])->for('afisha')?->get('month'))
        ->toBe('2026-01');
});

it('refuses a portion above the effective limit under the key of the portion', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    expect(refusalKeys(fn () => resolvePage([$catalog], ['b' => ['afisha' => ['per_page' => '21']]])))
        ->toBe(['b.afisha.per_page']);

    expect(resolvePage([$catalog], ['b' => ['afisha' => ['per_page' => '20']]])->for('afisha')?->perPage)
        ->toBe(20);
});

it('lets the query win over the setting of the block and the setting over the schema', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha', 'content' => ['per_page' => 8]]);

    expect(resolvePage([$catalog], [])->for('afisha')?->perPage)->toBe(8)
        ->and(resolvePage([$catalog], ['b' => ['afisha' => ['per_page' => '15']]])->for('afisha')?->perPage)
        ->toBe(15);
});

it('ignores the setting of a block whose schema does not allow it', function (): void {
    $feed = Make::block(attributes: ['type' => 'feed', 'anchor' => 'lenta', 'content' => ['per_page' => 3]]);

    expect(resolvePage([$feed], [])->for('lenta')?->perPage)->toBe(12);
});

it('normalizes the number of a page instead of rejecting it', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    expect(resolvePage([$catalog], ['b' => ['afisha' => ['page' => '0']]])->for('afisha')?->page)->toBe(1)
        ->and(resolvePage([$catalog], ['b' => ['afisha' => ['page' => '-3']]])->for('afisha')?->page)->toBe(1)
        ->and(resolvePage([$catalog], ['b' => ['afisha' => ['page' => 'nonsense']]])->for('afisha')?->page)->toBe(1)
        ->and(resolvePage([$catalog], ['b' => ['afisha' => ['page' => '900']]])->for('afisha')?->page)->toBe(900);
});

it('brings the accumulator to a multiple of the portion and clamps it by the ceiling', function (): void {
    $feed = Make::block(attributes: ['type' => 'feed', 'anchor' => 'lenta']);

    $shown = static fn (mixed $value): ?int => resolvePage(
        [$feed],
        ['b' => ['lenta' => ['shown' => $value]]],
    )->for('lenta')?->shown;

    expect(resolvePage([$feed], [])->for('lenta')?->shown)->toBe(12)
        ->and($shown('36'))->toBe(36)
        ->and($shown('30'))->toBe(24)
        ->and($shown('1'))->toBe(12)
        ->and($shown('0'))->toBe(12)
        ->and($shown('-5'))->toBe(12)
        ->and($shown('600'))->toBe(48)
        ->and(resolvePage([$feed], [])->for('lenta')?->maxShown)->toBe(48);
});

it('takes an opaque cursor only where the transport is the endpoint of the block', function (): void {
    $cursor = Make::block(attributes: ['type' => 'cursor_feed', 'anchor' => 'kursor']);
    $feed = Make::block(attributes: ['type' => 'feed', 'anchor' => 'lenta', 'sort_order' => 1]);

    $resolved = resolvePage([$cursor, $feed], [
        'b' => ['kursor' => ['cursor' => 'eyJrIjoxfQ'], 'lenta' => ['cursor' => 'eyJrIjoxfQ']],
    ]);

    expect($resolved->for('kursor')?->cursor)->toBe('eyJrIjoxfQ')
        ->and($resolved->for('kursor')?->shown)->toBeNull()
        ->and($resolved->for('lenta')?->cursor)->toBeNull()
        ->and($resolved->for('lenta')?->shown)->toBe(12);
});

it('passes a block without an anchor and a block that is not dynamic by', function (): void {
    $text = Make::block(attributes: ['type' => 'text', 'anchor' => 'about']);
    $unregistered = Make::block(attributes: ['type' => 'nonexistent', 'anchor' => 'unknown', 'sort_order' => 1]);

    $anchorless = new CmsBlock();
    $anchorless->type = 'catalog';
    $anchorless->anchor = null;

    expect(resolvePage([$text, $unregistered, $anchorless], [])->anchors())->toBe([]);
});

it('collects only the filtering values of a set', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);

    $resolved = resolvePage([$catalog], ['b' => ['afisha' => ['city' => 'berlin', 'page' => '2']]]);

    expect($resolved->for('afisha')?->filterValues())->toBe(['city' => 'berlin', 'month' => null]);
});

it('ignores a portion size in the query of a growing list', function (): void {
    $feed = Make::block(attributes: ['type' => 'feed', 'anchor' => 'lenta']);

    $resolved = resolvePage([$feed], ['b' => ['lenta' => ['per_page' => '3']]]);

    expect($resolved->for('lenta')?->perPage)->toBe(12)
        ->and($resolved->toQuery())->toBe([]);
});

it('lowers the effective limit to the one of the config when the config is the smaller', function (): void {
    $catalog = Make::block(attributes: ['type' => 'catalog', 'anchor' => 'afisha']);
    $resolution = limitedResolution(maxPerPage: 3, maxShown: 24);

    $refused = fn () => $resolution->resolve(
        [$catalog],
        BlockParameters::fromPageQuery(['b' => ['afisha' => ['per_page' => '10']]]),
    );

    expect($refused)->toThrow(ValidationException::class)
        ->and($resolution->resolve([$catalog], BlockParameters::none())->for('afisha')?->perPage)
        ->toBe(3);
});

it('lowers the ceiling of the slice to the one of the config as well', function (): void {
    $feed = Make::block(attributes: ['type' => 'feed', 'anchor' => 'lenta']);
    $resolution = limitedResolution(maxPerPage: 100, maxShown: 30);

    $resolved = $resolution->resolve(
        [$feed],
        BlockParameters::fromPageQuery(['b' => ['lenta' => ['shown' => '600']]]),
    );

    expect($resolved->for('lenta')?->maxShown)->toBe(24)
        ->and($resolved->for('lenta')?->shown)->toBe(24);
});

it('aligns a ceiling that is not a multiple of the portion, so the slice can reach it', function (): void {
    $open = Make::block(attributes: ['type' => 'open_feed', 'anchor' => 'otkrytaya']);

    $set = resolvePage([$open], ['b' => ['otkrytaya' => ['shown' => '1000']]])->for('otkrytaya');

    expect($set?->maxShown)->toBe(492)
        ->and($set?->shown)->toBe(492);
});

it('keeps a cursor out of the address of the page', function (): void {
    $cursor = Make::block(attributes: ['type' => 'cursor_feed', 'anchor' => 'kursor']);

    $resolved = resolvePage([$cursor], ['b' => ['kursor' => ['cursor' => 'eyJrIjoxfQ']]]);

    expect($resolved->toQuery())->toBe([]);
});

it('takes the mode the editor picked for his own block', function (): void {
    $block = Make::block(attributes: [
        'type' => 'switchable',
        'anchor' => 'podborka',
        'content' => ['pagination_mode' => 'more'],
    ]);

    $set = resolvePage([$block], ['b' => ['podborka' => ['shown' => '18']]])->for('podborka');

    expect($set?->mode())->toBe(PaginationMode::MORE)
        ->and($set?->perPage)->toBe(6)
        ->and($set?->shown)->toBe(18)
        ->and($set?->maxShown)->toBe(498);
});

it('falls back to the declared mode for a pick the schema does not allow', function (): void {
    $forbidden = Make::block(attributes: [
        'type' => 'switchable',
        'anchor' => 'zapreshcheno',
        'content' => ['pagination_mode' => 'pages'],
    ]);
    $nonsense = Make::block(attributes: [
        'type' => 'switchable',
        'anchor' => 'chepuha',
        'content' => ['pagination_mode' => 'nonsense'],
        'sort_order' => 1,
    ]);

    $resolved = resolvePage([$forbidden, $nonsense], [
        'b' => ['zapreshcheno' => ['page' => '3'], 'chepuha' => ['shown' => '18']],
    ]);

    expect($resolved->for('zapreshcheno')?->mode())->toBe(PaginationMode::NONE)
        ->and($resolved->for('zapreshcheno')?->page)->toBe(1)
        ->and($resolved->for('chepuha')?->mode())->toBe(PaginationMode::NONE)
        ->and($resolved->for('chepuha')?->shown)->toBeNull();
});

it('keeps the picked mode out of the content of a block whose schema fixes it', function (): void {
    $fixed = Make::block(attributes: [
        'type' => 'feed',
        'anchor' => 'lenta',
        'content' => ['pagination_mode' => 'pages'],
    ]);
    $switchable = Make::block(attributes: [
        'type' => 'switchable',
        'anchor' => 'podborka',
        'content' => ['pagination_mode' => 'more'],
        'sort_order' => 1,
    ]);

    expect($fixed->fresh()?->content ?? [])->not->toHaveKey('pagination_mode')
        ->and($switchable->fresh()?->content ?? [])->toBe(['pagination_mode' => 'more'])
        ->and(resolvePage([$fixed], [])->for('lenta')?->mode())->toBe(PaginationMode::MORE);
});

it('refuses a value of a filter whose set of values is empty right now', function (): void {
    $block = Make::block(attributes: ['type' => 'empty_set', 'anchor' => 'pustoy']);

    expect(refusalKeys(fn () => resolvePage([$block], ['b' => ['pustoy' => ['city' => 'berlin']]])))
        ->toBe(['b.pustoy.city'])
        // Without the parameter the block still opens.
        ->and(resolvePage([$block], [])->for('pustoy')?->get('city'))->toBeNull();
});

it('asks the provider of a filter once for checking and normalizing the same value', function (): void {
    app(BlockRegistryInterface::class)->register(CountedCatalogBlock::class);
    $block = Make::block(attributes: ['type' => 'counted_catalog', 'anchor' => 'afisha']);

    // A provider is a query of the installation; the value is checked against
    // its set and then normalized against the very same set.
    CountingCityOptions::reset();
    $city = resolvePage([$block], ['b' => ['afisha' => ['city' => 'berlin']]])->for('afisha')?->get('city');
    $sent = CountingCityOptions::$calls;

    CountingCityOptions::reset();
    $refused = refusalKeys(fn () => resolvePage([$block], ['b' => ['afisha' => ['city' => 'paris']]]));
    $refusal = CountingCityOptions::$calls;

    // An absent filter is neither checked nor normalized: its set is not read.
    CountingCityOptions::reset();
    resolvePage([$block], []);
    $absent = CountingCityOptions::$calls;

    expect($city)->toBe('berlin')
        ->and($sent)->toBe(1)
        ->and($refused)->toBe(['b.afisha.city'])
        ->and($refusal)->toBe(1)
        ->and($absent)->toBe(0);
});

it('reads the set of a filter again on the next resolution', function (): void {
    app(BlockRegistryInterface::class)->register(CountedCatalogBlock::class);
    $block = Make::block(attributes: ['type' => 'counted_catalog', 'anchor' => 'afisha']);

    // Nothing outlives one resolution: a city added between two requests has
    // to be accepted by the second one, see EnumParam::getOptions().
    CountingCityOptions::reset();
    resolvePage([$block], ['b' => ['afisha' => ['city' => 'berlin']]]);
    resolvePage([$block], ['b' => ['afisha' => ['city' => 'koeln']]]);

    expect(CountingCityOptions::$calls)->toBe(2);
});
