<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Tests\Fixtures\BuyAction;
use Ewk\Cms\Blocks\Tests\Fixtures\Make;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

function resolveLink(LinkKind $kind, array $attributes = [], bool $newTab = false): ResolvedLink
{
    return app(LinkResolverRegistry::class)->resolve(new Link($kind, $attributes, $newTab), 'ru');
}

it('resolves an anchor of the page being read into a bare fragment', function (): void {
    $resolved = resolveLink(LinkKind::ANCHOR, ['anchor' => 'raspisanie'], newTab: true);

    expect($resolved->toArray())->toBe([
        'kind' => 'anchor',
        'href' => '#raspisanie',
        'external' => false,
        'new_tab' => true,
        'action' => null,
    ]);
});

it('drops the hash an editor typed in front of the anchor', function (): void {
    expect(resolveLink(LinkKind::ANCHOR, ['anchor' => ' #raspisanie '])->href)->toBe('#raspisanie');
});

it('hands out a broken anchor instead of an address for a value that is not an anchor', function (string $anchor): void {
    $resolved = resolveLink(LinkKind::ANCHOR, ['anchor' => $anchor]);

    expect($resolved->kind)->toBe(LinkKind::ANCHOR)
        ->and($resolved->href)->toBeNull();
})->with([
    'not an anchor' => ['не якорь'],
    'capitals, a fragment being case sensitive' => ['Raspisanie'],
]);

it('leaves an anchor on another page broken while nothing resolves pages', function (): void {
    // The blocks own no pages: the address of one comes from the resolver of
    // the `page` kind, which the pages register.
    expect(resolveLink(LinkKind::ANCHOR, ['anchor' => 'kontakty', 'page' => '9f3c1a2b'])->href)->toBeNull();
});

it('resolves a file into the address of its record', function (): void {
    $media = Make::media('rider.pdf', ['mime' => 'application/pdf']);

    $resolved = resolveLink(LinkKind::FILE, ['file' => $media->uuid]);

    expect($resolved->href)->toBe($media->url())
        ->and($resolved->kind)->toBe(LinkKind::FILE);
});

it('marks a file served from another host external', function (): void {
    Config::set('app.url', 'https://example.test');
    Config::set('filesystems.disks.public.url', 'https://example.test/storage');
    Config::set('filesystems.disks.cdn', [
        'driver' => 'local',
        'root' => sys_get_temp_dir(),
        'url' => 'https://cdn.example.com/files',
    ]);

    $own = Make::media('own.pdf', ['mime' => 'application/pdf']);
    $foreign = Make::media('far.pdf', ['disk' => 'cdn', 'mime' => 'application/pdf']);

    expect(resolveLink(LinkKind::FILE, ['file' => $foreign->uuid])->external)->toBeTrue()
        ->and(resolveLink(LinkKind::FILE, ['file' => $own->uuid])->external)->toBeFalse();
});

it('hands out a broken file link for a record that is gone', function (): void {
    $resolved = resolveLink(LinkKind::FILE, ['file' => '9f3c1a2b-4e55-4c1e-9a3f-0b71d2c35e10']);

    expect($resolved->kind)->toBe(LinkKind::FILE)
        ->and($resolved->href)->toBeNull();
});

it('resolves an action into a full address and keeps the hint beside it', function (): void {
    app(ActionRegistry::class)->register(new BuyAction());

    $resolved = resolveLink(LinkKind::ACTION, ['action' => 'buy', 'params' => ['event' => 'icegergert']]);

    expect($resolved->toArray())->toBe([
        'kind' => 'action',
        'href' => '/tours/stand-up/icegergert#buy',
        'external' => false,
        'new_tab' => false,
        'action' => ['name' => 'buy', 'params' => ['event' => 'icegergert']],
    ]);
});

it('keeps the hint of an action whose address could not be built', function (): void {
    app(ActionRegistry::class)->register(new BuyAction());

    $resolved = resolveLink(LinkKind::ACTION, ['action' => 'buy', 'params' => []]);

    expect($resolved->href)->toBeNull()
        ->and($resolved->action)->toBe(['name' => 'buy', 'params' => []]);
});

it('tells a kind without a resolver apart from a target that cannot be reached', function (): void {
    $registry = app(LinkResolverRegistry::class);

    expect($registry->has(LinkKind::ANCHOR))->toBeTrue()
        ->and($registry->has(LinkKind::FILE))->toBeTrue()
        ->and($registry->has(LinkKind::ACTION))->toBeTrue()
        // Pages and catalogs belong to the pages, entities and routes to the
        // installation that declares them.
        ->and($registry->has(LinkKind::PAGE))->toBeFalse()
        ->and($registry->for(LinkKind::PAGE))->toBeNull();
});

it('never throws over a kind nothing resolves, so a public answer survives it', function (): void {
    $resolved = resolveLink(LinkKind::ROUTE, ['route' => 'auth.login'], newTab: true);

    expect($resolved->toArray())->toBe([
        'kind' => 'route',
        'href' => null,
        'external' => false,
        'new_tab' => true,
        'action' => null,
    ]);
});

it('refuses a value that is not an anchor in the form', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $attributes): bool => Validator::make(
        ['link' => ['kind' => 'anchor', 'attributes' => $attributes]],
        $rules,
    )->fails();

    // Nothing here owns pages, so a target on another page is checked for its
    // shape and no further: the anchors of that page are somebody else's rows.
    expect($fails(['anchor' => 'raspisanie']))->toBeFalse()
        ->and($fails(['anchor' => '#raspisanie', 'page' => '9f3c1a2b']))->toBeFalse()
        ->and($fails(['anchor' => 'Raspisanie']))->toBeTrue()
        ->and($fails(['anchor' => 'не якорь']))->toBeTrue();
});
