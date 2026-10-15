<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\MoonShineCms\Tests\Fixtures\BuyAction;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function lookupUrl(string $name, array $query = []): string
{
    return route('moonshine.cms.link.'.$name, $query);
}

/**
 * The options of an answer as a plain map, so a test asks what is offered and
 * not how the select of the panel reads it.
 *
 * @return array<string, string>
 */
function offered(array $rows): array
{
    $options = [];

    foreach ($rows as $row) {
        $options[$row['value']] = $row['label'];
    }

    return $options;
}

it('answers nobody outside the panel', function (): void {
    auth('moonshine')->logout();

    $this->get(lookupUrl('pages'))->assertRedirect();
});

it('offers the pages a link may point at, named the way an editor reads them', function (): void {
    $contacts = cmsPage(['slug' => 'contacts', 'name' => ['ru' => 'Контакты']]);
    cmsPage(['slug' => 'draft', 'name' => ['ru' => 'Черновик'], 'is_active' => false]);
    cmsPage(['type' => 'concert', 'slug' => 'concert', 'name' => ['ru' => 'Шаблон концерта']]);

    $options = offered($this->get(lookupUrl('pages'))->assertOk()->json());

    expect($options)->toHaveKey($contacts->uuid)
        ->and($options[$contacts->uuid])->toBe('Контакты (/contacts)')
        // A page switched off has no address to offer, and a template answers
        // a pattern instead of an address.
        ->and(implode(' ', $options))->not->toContain('Черновик')
        ->and(implode(' ', $options))->not->toContain('Шаблон концерта');
});

it('narrows the pages by what the editor typed', function (): void {
    cmsPage(['slug' => 'contacts', 'name' => ['ru' => 'Контакты']]);
    cmsPage(['slug' => 'about', 'name' => ['ru' => 'О нас']]);

    $options = offered($this->get(lookupUrl('pages', ['query' => 'contact']))->assertOk()->json());

    expect($options)->toHaveCount(1)
        ->and(implode(' ', $options))->toContain('Контакты');
});

it('searches the records of a class a page type stands over', function (): void {
    $concert = Catalogue::query()->create(['name' => 'Rammstein']);
    Catalogue::query()->create(['name' => 'Кино']);

    $options = offered($this->get(lookupUrl('entities', [
        'type' => Catalogue::class,
        'query' => 'ramm',
    ]))->assertOk()->json());

    expect($options)->toBe([Catalogue::class.'::'.$concert->getKey() => 'Rammstein']);
});

it('refuses a class nothing of this installation points at', function (): void {
    $this->get(lookupUrl('entities', ['type' => 'Illuminate\\Foundation\\Auth\\User']))
        ->assertOk()
        ->assertExactJson([]);
});

it('offers the sections of a page, named after the cards that carry them', function (): void {
    $page = cmsPage(['slug' => 'home-ish']);
    cmsBlock($page, ['type' => 'every_field', 'name' => 'Всё', 'anchor' => 'about', 'content' => [
        'title' => ['ru' => 'Про фестиваль'],
    ]]);
    cmsBlock($page, ['type' => 'text', 'name' => 'Без якоря', 'sort_order' => 1]);

    $options = offered($this->get(lookupUrl('anchors', ['page' => $page->uuid]))->assertOk()->json());

    // «Type: what the block says», exactly as the head of the card reads.
    expect($options)->toBe(['about' => 'Все поля: Про фестиваль']);
});

it('offers the registered actions by name and never by code', function (): void {
    app(ActionRegistry::class)->register(new BuyAction());

    $options = offered($this->get(lookupUrl('actions'))->assertOk()->json());

    // An action declares its code and its address and no caption of its own,
    // so the name comes from the dictionary of the panel.
    expect($options)->toHaveKey('buy')
        ->and($options['buy'])->not->toBe('buy')
        ->and($options['buy'])->toBe((string) __('moonshine-cms::links.actions.buy'));
});

it('offers the lists of a catalogue page and the values its filters accept', function (): void {
    $catalog = cmsPage(['slug' => 'afisha']);
    cmsBlock($catalog, ['type' => 'feed', 'name' => 'Афиша', 'anchor' => 'afisha', 'content' => [
        'title' => ['ru' => 'Ближайшие события'],
    ]]);

    $body = $this->get(lookupUrl('catalog', ['page' => $catalog->uuid]))->assertOk()->json();

    expect($body['lists'])->toHaveCount(1)
        ->and($body['lists'][0]['anchor'])->toBe('afisha')
        ->and($body['lists'][0]['label'])->toContain('Ближайшие события');

    $filters = array_column($body['filters'], null, 'name');

    expect($filters['category']['label'])->toBe('Категория')
        ->and($filters['category']['type'])->toBe('options')
        ->and($filters['category']['options'])->toBe(['music' => 'Музыка', 'theatre' => 'Театр'])
        // A filter with no declared set is typed instead of picked.
        ->and($filters['city']['type'])->toBe('text')
        ->and($filters['city']['options'])->toBe([]);
});

it('offers a date filter as a date and a yes-or-no filter as a choice of two', function (): void {
    $catalog = cmsPage(['slug' => 'afisha']);
    cmsBlock($catalog, ['type' => 'feed', 'name' => 'Афиша', 'anchor' => 'afisha']);

    $filters = array_column(
        $this->get(lookupUrl('catalog', ['page' => $catalog->uuid]))->assertOk()->json('filters'),
        null,
        'name',
    );

    expect($filters['opens_on']['type'])->toBe('date')
        ->and($filters['free']['type'])->toBe('options')
        ->and(array_values($filters['free']['options']))->toHaveCount(2);
});

it('says a page with no list on it offers nothing to filter', function (): void {
    $page = cmsPage(['slug' => 'contacts']);

    expect($this->get(lookupUrl('catalog', ['page' => $page->uuid]))->assertOk()->json())
        ->toBe(['lists' => [], 'filters' => []]);
});

it('opens an entity field on the names of the records it already holds', function (): void {
    $owner = editorOwner();
    $concert = Catalogue::query()->create(['name' => 'Rammstein']);
    Catalogue::query()->create(['name' => 'Кино']);

    cmsBlock($owner, [
        'type' => 'every_field',
        'name' => 'Всё',
        'content' => ['concert' => ['type' => Catalogue::class, 'id' => $concert->getKey()]],
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The field is searched over the wire, so the document carries the chosen
    // record and not the table.
    expect(plainUrls($html))->toContain('cms/link/entities')
        ->and($html)->toContain('Rammstein')
        ->and($html)->not->toContain('Кино');
});
