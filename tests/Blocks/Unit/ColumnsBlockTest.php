<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\Schema\Slot;

/**
 * @return list<int>
 */
function spansOf(?string $variant): array
{
    return array_map(
        static fn (Slot $slot): int => $slot->span,
        ColumnsBlock::slotsFor($variant),
    );
}

it('declares the twelve column grid of every shipped variant', function (): void {
    expect(spansOf('2'))->toBe([6, 6])
        ->and(spansOf('3'))->toBe([4, 4, 4])
        ->and(spansOf('4'))->toBe([3, 3, 3, 3])
        ->and(spansOf('1-2'))->toBe([4, 8]);
});

it('names the slots after their position and accepts any block in them', function (): void {
    $slots = ColumnsBlock::slotsFor('3');

    expect(array_map(static fn (Slot $slot): string => $slot->name, $slots))
        ->toBe(['col_1', 'col_2', 'col_3'])
        ->and($slots[0]->accepts('text'))->toBeTrue()
        ->and($slots[0]->min)->toBe(1);
});

it('falls back to two columns for a missing or unknown variant', function (): void {
    expect(spansOf(null))->toBe([6, 6])
        ->and(spansOf('does-not-exist'))->toBe([6, 6]);
});

it('accepts only the declared types when the slot carries a white list', function (): void {
    $slot = new Slot(name: 'only', title: 'Слот', allow: ['text']);

    expect($slot->accepts('text'))->toBeTrue()
        ->and($slot->accepts('toc'))->toBeFalse();
});

it('refuses a span outside the twelve column grid', function (): void {
    expect(fn () => new Slot(name: 'wide', title: 'Слот', span: 13))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a maximum below the minimum', function (): void {
    expect(fn () => new Slot(name: 'only', title: 'Слот', min: 2, max: 1))
        ->toThrow(InvalidArgumentException::class);
});
