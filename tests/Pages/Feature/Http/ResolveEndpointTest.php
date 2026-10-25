<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Events\PageResolved;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributor;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry;
use Ewk\Cms\Pages\Http\Resolve\ResolveDocument;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\Fixtures\Blocks\ListingBlock;
use Ewk\Cms\Pages\Tests\Fixtures\Concert;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/*
 * `GET /api/v1/resolve`. The endpoint belongs to this package, so the route is
 * registered by its own provider and these tests drive it over HTTP.
 *
 * Every page is published through `PublishOwner`: the public answer reads the
 * snapshot and a draft is a 404.
 */

beforeEach(function (): void {
    $registry = app(PageTypeRegistry::class);
    TestPageTypes::registerAll($registry);
    $registry->register(TestPageTypes::gig());
    $registry->register(TestPageTypes::recital());

    // Turning `Accept-Language` into a locale is middleware work and belongs to
    // the installation; the resolver answers in the locale of the application.
    app()->setLocale('ru');
});

function resolvablePage(string $slug, string $type = 'page'): CmsPage
{
    return app(PageTree::class)->create(new CmsPage([
        'type' => $type,
        'slug' => $slug,
        'name' => ['ru' => 'Условия', 'en' => 'Terms'],
        'layout_code' => 'text',
        'is_active' => true,
    ]));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function resolvableBlock(CmsPage $page, array $attributes = []): CmsBlock
{
    /** @var CmsBlock $block */
    $block = $page->blocks()->create($attributes + [
        'type' => TextBlock::code(),
        'name' => 'Текст',
        'content' => ['title' => ['ru' => 'Заголовок'], 'body' => ['ru' => 'Текст блока']],
        'is_active' => true,
    ]);

    return $block;
}

function publishForResolve(CmsPage $page): void
{
    app(PublishOwner::class)->handle($page);
}

it('answers a page of the tree with the whole document and the Vary header', function (): void {
    $page = resolvablePage('terms');
    $block = resolvableBlock($page);
    publishForResolve($page);

    $response = $this->getJson('/api/v1/resolve?path=/terms');

    $response->assertOk()
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.type', 'page')
        ->assertJsonPath('data.path', '/terms')
        ->assertJsonPath('data.page.uuid', $page->uuid)
        ->assertJsonPath('data.page.name', 'Условия')
        ->assertJsonPath('data.page.layout_code', 'text')
        ->assertJsonPath('data.subject', null)
        ->assertJsonPath('data.languages', ['ru', 'en', 'de-CH'])
        ->assertJsonPath('data.blocks.0.uuid', $block->uuid)
        ->assertJsonPath('data.blocks.0.code', 'text')
        ->assertJsonPath('data.blocks.0.data.body', 'Текст блока')
        ->assertJsonPath('data.blocks.0.children', []);

    // The date of publication travels beside the type: the header of the
    // `text` layout shows it.
    expect($response->json('data.page.published_at'))->toBeString();
});

it('reads the caption in the requested language and falls back to the default one', function (): void {
    $page = app(PageTree::class)->create(new CmsPage([
        'type' => 'page',
        'slug' => 'only-russian',
        'name' => ['ru' => 'Только по-русски'],
        'is_active' => true,
    ]));
    resolvableBlock($page);
    publishForResolve($page);

    app()->setLocale('en');

    $this->getJson('/api/v1/resolve?path=/only-russian')
        ->assertOk()
        ->assertJsonPath('data.page.name', 'Только по-русски');
});

it('answers the address of a singleton by the path its type declares', function (): void {
    $home = resolvablePage('home', 'home');
    resolvableBlock($home);
    publishForResolve($home);

    $this->getJson('/api/v1/resolve?path=%2F')
        ->assertOk()
        ->assertJsonPath('data.type', 'home')
        ->assertJsonPath('data.path', '/');
});

it('answers a template address with the subject the type declared a presenter for', function (): void {
    $concert = Make::concert('jazz', 'quartet');

    $template = resolvablePage('gig-template', 'gig');
    resolvableBlock($template);
    publishForResolve($template);

    $this->getJson('/api/v1/resolve?path=/gigs/quartet')
        ->assertOk()
        ->assertJsonPath('data.type', 'gig')
        ->assertJsonPath('data.path', '/gigs/quartet')
        // The blocks belong to the template, the address to the entity.
        ->assertJsonPath('data.page.uuid', $template->uuid)
        ->assertJsonPath('data.subject.type', 'gig')
        ->assertJsonPath('data.subject.slug', 'quartet')
        ->assertJsonPath('data.subject.data.id', $concert->id)
        ->assertJsonPath('data.subject.data.slug', 'quartet');
});

it('answers with an empty subject payload when the type declares no presenter', function (): void {
    Make::concert('jazz', 'solo');

    $template = resolvablePage('recital-template', 'recital');
    resolvableBlock($template);
    publishForResolve($template);

    $response = $this->getJson('/api/v1/resolve?path=/recitals/solo')
        ->assertOk()
        ->assertJsonPath('data.subject.type', 'recital')
        ->assertJsonPath('data.subject.slug', 'solo');

    expect($response->getContent())->toContain('"subject":{"type":"recital","slug":"solo","data":{}}');
});

it('reports a language prefix with 400 instead of hiding it behind a 404', function (): void {
    $page = resolvablePage('terms');
    resolvableBlock($page);
    publishForResolve($page);

    $response = $this->getJson('/api/v1/resolve?path=/ru/terms');

    $response->assertStatus(400)->assertJsonPath('code', 'bad_request');

    expect($response->json('message'))->toContain('ru');
});

it('answers 404 for a draft, for a page taken off publication and for an unknown address', function (): void {
    $draft = resolvablePage('draft');
    resolvableBlock($draft);

    // No snapshot at all: the page is still a draft.
    $this->getJson('/api/v1/resolve?path=/draft')->assertNotFound();

    publishForResolve($draft);
    $this->getJson('/api/v1/resolve?path=/draft')->assertOk();

    // The snapshot is kept, the address stays taken, the answer is a miss.
    $draft->update(['is_active' => false]);
    $this->getJson('/api/v1/resolve?path=/draft')->assertNotFound();

    $this->getJson('/api/v1/resolve?path=/nowhere')->assertNotFound();
});

it('demands the address', function (): void {
    $this->getJson('/api/v1/resolve')
        ->assertStatus(422)
        ->assertJsonPath('errors.path.0', __('validation.required', ['attribute' => 'path']));
});

it('does not divide the parameters of two lists on one page', function (): void {
    $page = resolvablePage('catalogue');

    $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Афиша',
        'anchor' => 'afisha',
        'content' => ['title' => ['ru' => 'Афиша']],
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Архив',
        'anchor' => 'arhiv',
        'content' => ['title' => ['ru' => 'Архив']],
        'sort_order' => 2,
        'is_active' => true,
    ]);

    publishForResolve($page);

    $this->getJson('/api/v1/resolve?path=/catalogue&b[afisha][page]=2&b[afisha][city]=praha')
        ->assertOk()
        ->assertJsonPath('data.blocks.0.data.page', 2)
        ->assertJsonPath('data.blocks.0.data.city', 'praha')
        // The neighbour opens in its first state: the sets are namespaced.
        ->assertJsonPath('data.blocks.1.data.page', 1)
        ->assertJsonPath('data.blocks.1.data.city', null);
});

it('answers 422 for a declared parameter carrying a value it does not accept', function (): void {
    $page = resolvablePage('catalogue');
    $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Афиша',
        'anchor' => 'afisha',
        'content' => [],
        'is_active' => true,
    ]);
    publishForResolve($page);

    $this->getJson('/api/v1/resolve?path=/catalogue&b[afisha][year]=1900')->assertStatus(422);

    // Above the effective ceiling of the schema.
    $this->getJson('/api/v1/resolve?path=/catalogue&b[afisha][per_page]=50')->assertStatus(422);

    // An undeclared parameter and a set under an unknown anchor are dropped
    // in silence: a public address collects foreign labels.
    $this->getJson('/api/v1/resolve?path=/catalogue&b[afisha][utm]=mail&b[gone][page]=3')->assertOk();
});

it('lets a contributor write into the document after the resolver has', function (): void {
    $page = resolvablePage('terms');
    resolvableBlock($page);
    publishForResolve($page);

    app(ResolveContributorRegistry::class)->register(new class () implements ResolveContributor {
        public function contribute(ResolveDocument $document, Request $request): void
        {
            $document->set('metadata', [
                'path' => $document->resolved()->path,
                'locale' => $document->locale(),
                'published' => $document->version() !== null,
            ]);
        }
    });

    $this->getJson('/api/v1/resolve?path=/terms')
        ->assertOk()
        ->assertJsonPath('data.metadata.path', '/terms')
        ->assertJsonPath('data.metadata.published', true);
});

it('attaches the subject to the request every options provider reads, so a declared filter is checked against the real set', function (): void {
    Make::concert('jazz', 'quartet');

    $template = resolvablePage('gig-template', 'gig');
    $template->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Афиша',
        'anchor' => 'afisha',
        'content' => [],
        'is_active' => true,
    ]);
    publishForResolve($template);

    // The set is computed from the entity of the page, so the value derived
    // from it has to be accepted. It is only accepted if the subject reached
    // the request the provider asks the container for — which is why the
    // controller must not take a form request of its own.
    $this->getJson('/api/v1/resolve?path=/gigs/quartet&b[afisha][pick]=quartet')
        ->assertOk()
        ->assertJsonPath('data.blocks.0.data.pick', 'quartet');

    // A value outside the set is a 422 and never a failure deeper in the
    // selection.
    $this->getJson('/api/v1/resolve?path=/gigs/quartet&b[afisha][pick]=nope')->assertStatus(422);
});

it('carries the Vary header on a miss as well as on a hit', function (): void {
    $response = $this->getJson('/api/v1/resolve?path=/nowhere');

    $response->assertNotFound()->assertHeader('Vary', 'Accept-Language');

    expect($response->json())->toBe([
        'message' => __('cms-pages::resolve.not_found'),
        'code' => 'not_found',
    ]);
});

it('keeps answering the published state while the draft moves on', function (): void {
    $page = resolvablePage('terms');
    $block = resolvableBlock($page, ['content' => ['title' => ['ru' => 'Было'], 'body' => ['ru' => 'Старый текст']]]);
    publishForResolve($page);

    // The live rows are the draft the editor works on; the visitor reads the
    // snapshot.
    $block->update(['content' => ['title' => ['ru' => 'Стало'], 'body' => ['ru' => 'Новый текст']]]);
    $page->blocks()->create([
        'type' => TextBlock::code(),
        'name' => 'Добавленный',
        'content' => ['body' => ['ru' => 'Ещё не опубликован']],
        'sort_order' => 5,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/resolve?path=/terms')
        ->assertOk()
        ->assertJsonCount(1, 'data.blocks')
        ->assertJsonPath('data.blocks.0.data.body', 'Старый текст');

    publishForResolve($page);

    $this->getJson('/api/v1/resolve?path=/terms')
        ->assertOk()
        ->assertJsonCount(2, 'data.blocks')
        ->assertJsonPath('data.blocks.0.data.body', 'Новый текст')
        ->assertJsonPath('data.blocks.1.data.body', 'Ещё не опубликован');
});

it('tells the installation a whole page was answered, with the subject of the address', function (): void {
    Event::fake([PageResolved::class]);

    $concert = Make::concert('jazz', 'quartet');

    $template = resolvablePage('gig-template', 'gig');
    resolvableBlock($template);
    publishForResolve($template);

    $this->getJson('/api/v1/resolve?path=/gigs/quartet', ['X-Probe' => 'yes'])->assertOk();

    Event::assertDispatchedTimes(PageResolved::class, 1);
    Event::assertDispatched(PageResolved::class, function (PageResolved $event) use ($concert, $template): bool {
        return $event->subject() instanceof Concert
            && $event->subject()->is($concert)
            && $event->page()->is($template)
            && $event->request->header('X-Probe') === 'yes';
    });
});

it('fires nothing on a miss, on a draft and on a language prefix', function (): void {
    Event::fake([PageResolved::class]);

    $draft = resolvablePage('draft');
    resolvableBlock($draft);

    $this->getJson('/api/v1/resolve?path=/draft')->assertNotFound();
    $this->getJson('/api/v1/resolve?path=/nowhere')->assertNotFound();
    $this->getJson('/api/v1/resolve?path=/ru/draft')->assertStatus(400);

    Event::assertNotDispatched(PageResolved::class);
});
