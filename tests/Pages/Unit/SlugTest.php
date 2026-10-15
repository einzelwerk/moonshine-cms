<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Exceptions\InvalidSlugException;
use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Support\Slug;

it('accepts a strict kebab segment', function (string $slug): void {
    expect(Slug::isValid($slug))->toBeTrue();
})->with(['about', 'stand-up-action', 'tour-2026', '2026']);

it('refuses everything that is not one kebab segment', function (string $slug): void {
    expect(Slug::isValid($slug))->toBeFalse();
})->with([
    'empty' => [''],
    'capitals' => ['About'],
    'spaces' => ['stand up'],
    'a slash is hidden nesting' => ['info/help'],
    'underscores' => ['stand_up'],
    'double hyphen' => ['stand--up'],
    'leading hyphen' => ['-about'],
    'trailing hyphen' => ['about-'],
    'cyrillic' => ['о-нас'],
]);

it('refuses a slug over the limit', function (): void {
    $slug = str_repeat('a', PageLimits::SLUG_LENGTH + 1);

    expect(Slug::isValid($slug))->toBeFalse()
        ->and(fn () => Slug::assert($slug))->toThrow(InvalidSlugException::class);
});

it('generates a kebab slug with transliteration of the source language', function (): void {
    expect(Slug::generate('Стендап Экшн', 'ru'))->toBe('stendap-ekshn')
        ->and(Slug::generate('Hello   World!'))->toBe('hello-world');
});

it('caps a generated slug without leaving a trailing hyphen', function (): void {
    $slug = Slug::generate(str_repeat('ab ', 60));

    expect(mb_strlen($slug))->toBeLessThanOrEqual(PageLimits::SLUG_LENGTH)
        ->and(Slug::isValid($slug))->toBeTrue();
});

it('refuses to invent a slug out of a source that has none', function (): void {
    expect(fn () => Slug::generate('!!! ???'))->toThrow(InvalidSlugException::class);
});
