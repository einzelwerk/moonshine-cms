<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Http\Payload\FiltersPayload;

it('returns the chosen value next to the options the block computed', function (): void {
    $payload = FiltersPayload::make(FiltersPayload::MODE_INLINE, [
        'month' => [
            'value' => '2026-01',
            'options' => [
                ['value' => null, 'label' => 'Все'],
                ['value' => '2026-01', 'label' => 'Январь'],
            ],
        ],
    ]);

    expect($payload)->toBe([
        'mode' => 'inline',
        'fields' => [
            'month' => [
                'value' => '2026-01',
                'options' => [
                    ['value' => null, 'label' => 'Все'],
                    ['value' => '2026-01', 'label' => 'Январь'],
                ],
            ],
        ],
    ]);
});

it('accepts a map of value to label as well', function (): void {
    $payload = FiltersPayload::make(FiltersPayload::MODE_INLINE, [
        'city' => ['value' => 'berlin', 'options' => ['berlin' => 'Берлин', 'koeln' => 'Кёльн']],
    ]);

    expect($payload['fields']['city']['options'])->toBe([
        ['value' => 'berlin', 'label' => 'Берлин'],
        ['value' => 'koeln', 'label' => 'Кёльн'],
    ]);
});

it('adds the target of the redirect mode and nothing in the inline one', function (): void {
    $redirect = FiltersPayload::make(FiltersPayload::MODE_REDIRECT, [], '/events?date_from=2026-09-12');

    expect(json_encode($redirect))->toBe('{"mode":"redirect","fields":{},"redirect":{"href":"\\/events?date_from=2026-09-12"}}')
        ->and(FiltersPayload::make(FiltersPayload::MODE_INLINE, [], '/events'))
        ->not->toHaveKey('redirect');
});

it('falls back to an empty option list for a filter without values', function (): void {
    $payload = FiltersPayload::make(FiltersPayload::MODE_INLINE, ['city' => ['value' => null]]);

    expect($payload['fields']['city'])->toBe(['value' => null, 'options' => []]);
});

it('sends no fields as an empty JSON object, never as an empty list', function (): void {
    expect(json_encode(FiltersPayload::make(FiltersPayload::MODE_INLINE, [])))
        ->toBe('{"mode":"inline","fields":{}}');
});
