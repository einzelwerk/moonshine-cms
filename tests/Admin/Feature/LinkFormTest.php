<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\MoonShineCms\Tests\Fixtures\BuyAction;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;
use Ewk\MoonShineCms\Tests\Fixtures\EditorOwner;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

/**
 * One card carrying one link of every kind. The block declares no narrowing,
 * so the picker offers the whole cascade and the round trip is the same one
 * every screen of the panel makes.
 *
 * @param  array<string, mixed>  $link
 * @return array<string, mixed>  the stored link
 */
function saveLink(EditorOwner $owner, array $link): array
{
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    test()->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'is_active' => true,
        'content' => ['title' => ['ru' => 'Заголовок'], 'cta' => $link],
    ])->assertOk();

    $stored = $block->refresh()->content['cta'] ?? [];

    return is_array($stored) ? $stored : [];
}

it('saves a page link and opens the form on the name of that page', function (): void {
    $owner = editorOwner();
    $page = cmsPage(['slug' => 'contacts', 'name' => ['ru' => 'Контакты']]);

    $stored = saveLink($owner, ['kind' => 'page', 'attributes' => ['page' => $page->uuid], 'new_tab' => '1']);

    expect($stored)->toBe([
        'kind' => 'page',
        'attributes' => ['page' => $page->uuid],
        'new_tab' => true,
    ]);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('Контакты (/contacts)')
        ->and($html)->toContain("choice: 'page'");
});

it('saves an entity link out of one packed option and opens it on the name of the record', function (): void {
    $owner = editorOwner();
    $concert = Catalogue::query()->create(['name' => 'Rammstein']);

    $stored = saveLink($owner, [
        'kind' => 'entity',
        'attributes' => ['type' => Catalogue::class, 'id' => (string) $concert->getKey()],
        'new_tab' => '0',
    ]);

    expect($stored['kind'])->toBe('entity')
        ->and($stored['attributes']['type'])->toBe(Catalogue::class)
        ->and($stored['attributes']['id'])->toBe((string) $concert->getKey());

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // The entry of the first select is the class, and the record comes back
    // with its name and never with its key alone.
    expect($html)->toContain("choice: 'entity:".addslashes(Catalogue::class)."'")
        ->and($html)->toContain('Rammstein');
});

it('saves a section of the page it is edited on without naming that page', function (): void {
    $page = cmsPage(['slug' => 'home-ish']);
    $block = cmsBlock($page, ['type' => 'feed', 'name' => 'Лента', 'anchor' => 'feed']);
    $card = cmsBlock($page, ['type' => 'every_field', 'name' => 'Всё', 'sort_order' => 1]);

    $this->post(pageEditorUrl('saveCmsBlock', $page), [
        'block' => $card->getKey(),
        'version' => blockVersion($card),
        'name' => 'Всё',
        'is_active' => true,
        'content' => ['title' => ['ru' => 'Заголовок'], 'cta' => [
            'kind' => 'anchor',
            'attributes' => ['anchor' => $block->anchor],
        ]],
    ])->assertOk();

    $stored = $card->refresh()->content['cta'] ?? [];

    // Inside the page the link is the bare fragment, so no page is stored,
    // see the resolver of the anchor kind.
    expect($stored)->toBe(['kind' => 'anchor', 'attributes' => ['anchor' => 'feed'], 'new_tab' => false]);

    $html = $this->get(pageResource()->blockEditorPageUrl($page->getKey()))->assertOk()->getContent();

    expect($html)->toContain("ownerPage: '".$page->uuid."'")
        ->and($html)->toContain("anchor: 'feed'");
});

it('saves a file of the library and opens the form on its name', function (): void {
    $owner = editorOwner();
    $media = CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'cms/rules.pdf',
        'original_name' => 'rules.pdf',
        'name' => 'Правила',
        'mime' => 'application/pdf',
        'size' => 4096,
    ]);

    $stored = saveLink($owner, ['kind' => 'file', 'attributes' => ['file' => $media->uuid]]);

    expect($stored['attributes']['file'])->toBe($media->uuid);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // A file is chosen in the library and not out of a list of the last fifty
    // uploads: the name, the thumbnail and the button that opens the dialog,
    // over the bare uuid the link stores.
    expect($html)->toContain('Правила')
        ->and($html)->toContain((string) __('moonshine-cms::links.choose_file'))
        ->and($html)->toContain('cms-link__file')
        ->and(plainUrls($html))->toContain('/cms/media/picker')
        ->and(plainUrls($html))->not->toContain('/cms/link/files');
});

it('saves an action of the registry and offers it by name', function (): void {
    app(ActionRegistry::class)->register(new BuyAction());
    $owner = editorOwner();

    $stored = saveLink($owner, ['kind' => 'action', 'attributes' => ['action' => 'buy']]);

    expect($stored['attributes']['action'])->toBe('buy');

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    expect($html)->toContain('<option value="buy" selected>'.__('moonshine-cms::links.actions.buy').'</option>');
});

it('saves a service address of the frontend picked out of the manifest', function (): void {
    config()->set('cms-pages.frontend_routes', ['auth.login' => '/auth/login']);
    $owner = editorOwner();

    $stored = saveLink($owner, ['kind' => 'route', 'attributes' => ['route' => 'auth.login']]);

    expect($stored['attributes']['route'])->toBe('auth.login');

    // The editor picks an address and never a route name.
    expect($this->get(blocksPage($owner))->assertOk()->getContent())
        ->toContain('<option value="auth.login" selected>/auth/login</option>');
});

it('saves the three plain targets as typed', function (array $link, string $marker): void {
    $owner = editorOwner();

    $stored = saveLink($owner, $link);

    expect($stored['attributes'])->toBe($link['attributes'])
        ->and($this->get(blocksPage($owner))->assertOk()->getContent())->toContain($marker);
})->with([
    'an external address' => [
        ['kind' => 'url', 'attributes' => ['url' => 'https://example.test/page']],
        'value="https://example.test/page"',
    ],
    'an e-mail' => [
        ['kind' => 'email', 'attributes' => ['email' => 'hello@example.test']],
        'value="hello@example.test"',
    ],
    'a phone number' => [
        ['kind' => 'phone', 'attributes' => ['phone' => '+420123456789']],
        'value="+420123456789"',
    ],
]);

it('refuses an address that is not one and says so on the field', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'is_active' => true,
        'content' => ['title' => ['ru' => 'Заголовок'], 'cta' => [
            'kind' => 'url',
            'attributes' => ['url' => 'javascript:alert(1)'],
        ]],
    ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(422)->assertJsonValidationErrors(['content.cta']);
});

it('offers no kind an installation cannot resolve and never a uuid to type', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $html = $this->get(blocksPage($owner))->assertOk()->getContent();

    // Nothing of the old form survives: no type of an entity typed by hand, no
    // key of a record, no pair of a filter key and its value.
    expect($html)->not->toContain('name="content[cta][attributes][type]"')
        ->and($html)->not->toContain('name="content[cta][attributes][id]"')
        ->and($html)->toContain('data-cms-link');
});
