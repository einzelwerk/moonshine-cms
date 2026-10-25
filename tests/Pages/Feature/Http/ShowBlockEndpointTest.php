<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Events\PageResolved;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\Fixtures\Blocks\ListingBlock;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Support\Facades\Event;

/*
 * `GET /api/v1/blocks/{uuid}`.
 *
 * The endpoint never looks a block up by its uuid alone: it resolves the page
 * of `path` first and then looks for the uuid inside the snapshot of that
 * page, so a uuid is not an address to pull an arbitrary block out of.
 */

beforeEach(function (): void {
    $registry = app(PageTypeRegistry::class);
    TestPageTypes::registerAll($registry);
    $registry->register(TestPageTypes::gig());
    app()->setLocale('ru');
});

function listingOn(CmsPage $page, string $anchor = 'afisha'): CmsBlock
{
    /** @var CmsBlock $block */
    $block = $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Афиша',
        'anchor' => $anchor,
        'content' => ['title' => ['ru' => 'Афиша']],
        'is_active' => true,
    ]);

    return $block;
}

function publishPage(CmsPage $page): void
{
    app(PublishOwner::class)->handle($page);
}

it('answers the envelope of one dynamic block, with its own flat parameters', function (): void {
    $page = Make::page('catalogue');
    $block = listingOn($page);
    publishPage($page);

    $response = $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue&page=2&city=praha');

    $response->assertOk()
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.uuid', $block->uuid)
        ->assertJsonPath('data.code', 'listing')
        ->assertJsonPath('data.anchor', 'afisha')
        ->assertJsonPath('data.children', [])
        // The parameters come without a namespace: the request carries one
        // block, and the anchor is read from the snapshot.
        ->assertJsonPath('data.data.page', 2)
        ->assertJsonPath('data.data.city', 'praha');

    // `path` is the address of the page and never a parameter of the block.
    expect($response->json('data.data'))->not->toHaveKey('path');
});

it('demands the address of the page', function (): void {
    $page = Make::page('catalogue');
    $block = listingOn($page);
    publishPage($page);

    $this->getJson('/api/v1/blocks/'.$block->uuid)
        ->assertStatus(422)
        ->assertJsonPath('errors.path.0', __('validation.required', ['attribute' => 'path']));
});

it('answers 404 for a uuid that belongs to another page', function (): void {
    $mine = Make::page('catalogue');
    listingOn($mine);
    publishPage($mine);

    $other = Make::page('archive');
    $stranger = listingOn($other, 'arhiv');
    publishPage($other);

    $this->getJson('/api/v1/blocks/'.$stranger->uuid.'?path=/catalogue')
        ->assertNotFound()
        ->assertExactJson(['message' => __('cms-pages::resolve.block_not_found'), 'code' => 'not_found']);
    $this->getJson('/api/v1/blocks/'.$stranger->uuid.'?path=/archive')->assertOk();
});

it('answers 404 for a block that is not dynamic', function (): void {
    $page = Make::page('terms');
    /** @var CmsBlock $static */
    $static = $page->blocks()->create([
        'type' => TextBlock::code(),
        'name' => 'Текст',
        'anchor' => 'text',
        'content' => ['body' => ['ru' => 'Текст']],
        'is_active' => true,
    ]);
    publishPage($page);

    $this->getJson('/api/v1/blocks/'.$static->uuid.'?path=/terms')->assertNotFound();
});

it('answers 404 for an unpublished page, for an unknown uuid and for a switched off block', function (): void {
    $page = Make::page('catalogue');
    $block = listingOn($page);

    // No snapshot yet: a draft is a 404 and not a preview.
    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue')->assertNotFound();

    publishPage($page);
    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue')->assertOk();

    $this->getJson('/api/v1/blocks/11111111-2222-3333-4444-555555555555?path=/catalogue')->assertNotFound();

    // The snapshot is what a visitor reads, so switching the block off shows
    // only after the next publication.
    $block->update(['is_active' => false]);
    publishPage($page);
    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue')->assertNotFound();
});

it('answers 422 for a declared parameter carrying a value it does not accept', function (): void {
    $page = Make::page('catalogue');
    $block = listingOn($page);
    publishPage($page);

    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue&year=1900')->assertStatus(422);
    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue&per_page=50')->assertStatus(422);
});

it('reports a language prefix in the path of the page with 400', function (): void {
    $page = Make::page('catalogue');
    $block = listingOn($page);
    publishPage($page);

    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/ru/catalogue')
        ->assertStatus(400)
        ->assertJsonPath('code', 'bad_request');
});

it('checks a declared filter against the set of the subject of the page', function (): void {
    $concert = \Ewk\Cms\Pages\Tests\Fixtures\Concert::query()->create([
        'series_id' => \Ewk\Cms\Pages\Tests\Fixtures\Series::query()->firstOrCreate(['slug' => 'jazz'])->id,
        'slug' => 'quartet',
        'is_active' => true,
        'is_hidden' => false,
    ]);

    $template = app(PageTree::class)->create(new CmsPage([
        'type' => 'gig',
        'slug' => 'gig-template',
        'name' => ['ru' => 'Шаблон'],
        'is_active' => true,
    ]));
    $block = listingOn($template);
    publishPage($template);

    // The subject has to be on the request the options provider reads out of
    // the container, or a value it does offer would come back as a 422.
    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/gigs/quartet&pick='.$concert->slug)
        ->assertOk()
        ->assertJsonPath('data.data.pick', 'quartet');

    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/gigs/quartet&pick=nope')->assertStatus(422);
});

it('carries the Vary header on a miss as well as on a hit', function (): void {
    $this->getJson('/api/v1/blocks/11111111-2222-3333-4444-555555555555?path=/nowhere')
        ->assertNotFound()
        ->assertHeader('Vary', 'Accept-Language');
});

it('is not a view of the page: the event of a resolved page is not fired', function (): void {
    Event::fake([PageResolved::class]);

    $page = Make::page('catalogue');
    $block = listingOn($page);
    publishPage($page);

    $this->getJson('/api/v1/blocks/'.$block->uuid.'?path=/catalogue')->assertOk();

    Event::assertNotDispatched(PageResolved::class);
});
