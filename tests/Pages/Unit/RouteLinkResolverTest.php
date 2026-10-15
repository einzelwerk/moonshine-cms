<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Pages\Links\RouteLinkResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    Config::set('cms-pages.frontend_routes', [
        'auth.login' => '/auth/login',
        'order' => '/orders/{uuid}',
    ]);
});

function routeLink(mixed $name, mixed $params = null): Link
{
    return new Link(LinkKind::ROUTE, ['route' => $name, 'params' => $params]);
}

it('builds the address of a route out of the manifest', function (): void {
    $resolved = app(RouteLinkResolver::class)->resolve(routeLink('auth.login'), 'ru');

    expect($resolved->toArray())->toBe([
        'kind' => 'route',
        'href' => '/auth/login',
        'external' => false,
        'new_tab' => false,
        'action' => null,
    ]);
});

it('substitutes the parameters a parametric route declares', function (): void {
    $href = app(RouteLinkResolver::class)
        ->resolve(routeLink('order', ['uuid' => '9f3c1a2b']), 'ru')
        ->href;

    expect($href)->toBe('/orders/9f3c1a2b');
});

it('hands out a broken link for an unknown route and for a missing parameter', function (): void {
    $resolver = app(RouteLinkResolver::class);

    expect($resolver->resolve(routeLink('dashboard.invoices'), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(routeLink('order'), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(routeLink('order', ['uuid' => '']), 'ru')->href)->toBeNull();
});

it('refuses an unknown route and an unfilled parameter in the form', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $attributes): bool => Validator::make(
        ['link' => ['kind' => 'route', 'attributes' => $attributes]],
        $rules,
    )->fails();

    expect($fails(['route' => 'auth.login']))->toBeFalse()
        ->and($fails(['route' => 'order', 'params' => ['uuid' => '9f3c1a2b']]))->toBeFalse()
        ->and($fails(['route' => 'dashboard.invoices']))->toBeTrue()
        ->and($fails(['route' => 'order']))->toBeTrue();
});

it('hands out a broken link for a parameter that would restructure the address', function (): void {
    $resolver = app(RouteLinkResolver::class);

    // One parameter fills one segment; a value carrying a separator is a
    // different address, so it gets no address at all.
    expect($resolver->resolve(routeLink('order', ['uuid' => '9f3c/../admin']), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(routeLink('order', ['uuid' => '9f3c?next=//evil.com']), 'ru')->href)->toBeNull();
});
