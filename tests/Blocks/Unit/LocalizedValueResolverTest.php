<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;

it('returns the requested language when it has a value', function (): void {
    expect(LocalizedValueResolver::resolve(['ru' => 'Заголовок', 'en' => 'Title'], 'en', 'ru'))->toBe('Title');
});

it('falls back to the default language when the requested one is missing or empty', function (): void {
    expect(LocalizedValueResolver::resolve(['ru' => 'Заголовок'], 'en', 'ru'))->toBe('Заголовок')
        ->and(LocalizedValueResolver::resolve(['ru' => 'Заголовок', 'en' => ''], 'en', 'ru'))->toBe('Заголовок');
});

it('returns an empty value when neither the requested nor the default language has one', function (): void {
    expect(LocalizedValueResolver::resolve(['en' => ''], 'en', 'ru'))->toBe('')
        ->and(LocalizedValueResolver::resolve([], 'en', 'ru'))->toBe('')
        ->and(LocalizedValueResolver::resolve(null, 'en', 'ru'))->toBe('');
});

it('never picks the first non-empty language, because that mixes languages on one page', function (): void {
    $stored = ['ru' => '', 'en' => null, 'de' => 'Überschrift'];

    expect(LocalizedValueResolver::resolve($stored, 'en', 'ru'))->toBe('');
});

it('passes a shared value through unchanged', function (): void {
    expect(LocalizedValueResolver::resolve('plain', 'en', 'ru'))->toBe('plain');
});
