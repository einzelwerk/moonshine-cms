<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Support\SiteUrl;
use Illuminate\Support\Facades\Config;

function siteUrl(): SiteUrl
{
    return app(SiteUrl::class);
}

it('puts the language of the request into every address', function (): void {
    expect(siteUrl()->absolute('/afisha', 'en'))->toBe('https://example.com/en/afisha');
});

it('ends the address of the home page with the language and no slash', function (): void {
    expect(siteUrl()->absolute('/', 'ru'))->toBe('https://example.com/ru');
});

it('keeps one slash however the configured site address and the path are written', function (): void {
    Config::set('cms-seo.site_url', 'https://example.com/');

    expect(siteUrl()->absolute('afisha/', 'ru'))->toBe('https://example.com/ru/afisha');
});

it('omits the prefix entirely on an installation without one', function (): void {
    Config::set('cms-seo.locale_prefix', 'never');

    expect(siteUrl()->absolute('/afisha', 'ru'))->toBe('https://example.com/afisha')
        ->and(siteUrl()->absolute('/', 'ru'))->toBe('https://example.com/');
});

it('falls back to the address of the application when the site address is not configured', function (): void {
    Config::set('cms-seo.site_url', null);
    Config::set('app.url', 'https://fallback.test');

    expect(siteUrl()->absolute('/afisha', 'ru'))->toBe('https://fallback.test/ru/afisha');
});

it('treats an unknown prefix strategy as the default one', function (): void {
    Config::set('cms-seo.locale_prefix', 'as-needed');

    expect(siteUrl()->absolute('/afisha', 'de'))->toBe('https://example.com/de/afisha');
});

it('refuses to build an address without a language while the prefix is required', function (): void {
    expect(fn (): string => siteUrl()->absolute('/afisha', ''))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): string => siteUrl()->absolute('/afisha', '/'))
        ->toThrow(InvalidArgumentException::class);
});

it('needs no language at all on an installation without a prefix', function (): void {
    Config::set('cms-seo.locale_prefix', 'never');

    expect(siteUrl()->absolute('/afisha', ''))->toBe('https://example.com/afisha');
});
