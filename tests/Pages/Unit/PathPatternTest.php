<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;
use Ewk\Cms\Pages\Support\PathPattern;

it('reads the placeholders of a template', function (): void {
    $pattern = PathPattern::fromString('/tours/{tour}/{event}');

    expect($pattern->pattern)->toBe('/tours/{tour}/{event}')
        ->and($pattern->placeholders())->toBe(['tour', 'event'])
        ->and($pattern->isStatic())->toBeFalse()
        ->and(PathPattern::fromString('/events')->isStatic())->toBeTrue();
});

it('matches an address and gives back its placeholder values', function (): void {
    $pattern = PathPattern::fromString('/tours/{tour}/{event}');

    expect($pattern->match(['tours', 'stand-up', 'night']))->toBe(['tour' => 'stand-up', 'event' => 'night']);
});

it('does not match an address of another shape', function (array $segments): void {
    expect(PathPattern::fromString('/tours/{tour}/{event}')->match($segments))->toBeNull();
})->with([
    'too short' => [['tours', 'stand-up']],
    'too long' => [['tours', 'stand-up', 'night', 'extra']],
    'another static segment' => [['places', 'stand-up', 'night']],
]);

it('builds the canonical address back from values', function (): void {
    expect(PathPattern::fromString('/tours/{tour}/{event}')->build(['tour' => 'stand-up', 'event' => 'night']))
        ->toBe('/tours/stand-up/night');
});

it('refuses a malformed placeholder instead of treating it as text', function (): void {
    expect(fn () => PathPattern::fromString('/tours/{tour'))->toThrow(InvalidPageTypeException::class);
});

it('refuses a value that is not one segment instead of letting it restructure the address', function (string $value): void {
    expect(PathPattern::fromString('/tours/{tour}')->build(['tour' => $value]))->toBeNull();
})->with([
    'a slash' => ['stand-up/secret'],
    'an escaped slash' => ['stand-up%2Fsecret'],
    'a query' => ['stand-up?next=//evil.com'],
    'a fragment' => ['stand-up#buy'],
    'nothing at all' => ['  '],
]);
