<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\BooleanParam;
use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\IntegerParam;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Tests\Fixtures\CityOptions;
use Ewk\Cms\Blocks\Tests\Fixtures\EmptyOptions;

it('builds the neutral rules of a parameter from its bounds', function (): void {
    $param = IntegerParam::make('radius')->min(1)->max(50);

    expect($param->rules())->toBe(['integer', 'min:1', 'max:50']);
});

it('is not a filter and not nullable until it says so', function (): void {
    $param = StringParam::make('month');

    expect($param->isFilter())->toBeFalse()
        ->and($param->isNullable())->toBeFalse()
        ->and($param->getDefault())->toBeNull();
});

it('wraps a declared pattern into a delimiter the pattern does not use', function (): void {
    expect(StringParam::make('month')->pattern('^\d{4}-(0[1-9]|1[0-2])$')->rules())
        ->toBe(['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'])
        ->and(StringParam::make('path')->pattern('^/tours/')->rules())
        ->toBe(['string', 'regex:#^/tours/#']);
});

it('normalizes a value into its declared type', function (): void {
    expect(IntegerParam::make('page')->normalize('7'))->toBe(7)
        ->and(StringParam::make('month')->normalize('  2026-01  '))->toBe('2026-01')
        ->and(StringParam::make('month')->normalize('   '))->toBeNull()
        ->and(DateParam::make('date')->normalize('2026-09-12'))->toBe('2026-09-12')
        ->and(BooleanParam::make('past')->normalize('false'))->toBeFalse()
        ->and(BooleanParam::make('past')->normalize('1'))->toBeTrue()
        ->and(IntegerParam::make('page')->normalize(['7']))->toBeNull();
});

it('takes the values of an enum parameter from a provider at the moment it asks', function (): void {
    $param = EnumParam::make('city')->options(CityOptions::class);

    expect($param->getOptions())->toBe(['berlin' => 'Берлин', 'koeln' => 'Кёльн'])
        ->and($param->getOptionsDeclaration())->toBe(CityOptions::class)
        ->and(array_map('strval', $param->rules()))->toBe(['in:"berlin","koeln"'])
        ->and($param->normalize('berlin'))->toBe('berlin');
});

it('quotes the values of a set instead of joining them by a comma', function (): void {
    $param = EnumParam::make('city')->options(['berlin, de' => 'Берлин']);

    expect(array_map('strval', $param->rules()))->toBe(['in:"berlin, de"'])
        ->and($param->normalize('berlin, de'))->toBe('berlin, de');
});

it('checks the trimmed value of a string parameter, not the raw one', function (): void {
    $param = StringParam::make('month')->pattern('^\d{4}-(0[1-9]|1[0-2])$');

    expect($param->valueForValidation('  2026-01  '))->toBe('2026-01')
        ->and(IntegerParam::make('page')->valueForValidation(' 7 '))->toBe(' 7 ');
});

it('hands an integer key of an enum parameter back as an integer', function (): void {
    $param = EnumParam::make('city')->options([10 => 'Берлин', 20 => 'Кёльн']);

    expect($param->normalize('10'))->toBe(10)
        ->and(array_map('strval', $param->rules()))->toBe(['in:"10","20"']);
});

it('declares the date format it validates by', function (): void {
    expect(DateParam::make('date')->rules())->toBe(['date_format:Y-m-d'])
        ->and(DateParam::make('month')->format('Y-m')->rules())->toBe(['date_format:Y-m']);
});

it('refuses every value while the set of an enum parameter is empty', function (): void {
    $declared = EnumParam::make('city')->options([]);
    $computed = EnumParam::make('city')->options(EmptyOptions::class)->nullable();

    expect(array_map('strval', $declared->rules()))->toBe(['in:'])
        ->and(array_map('strval', $computed->rules()))->toBe(['in:'])
        ->and(validator(['city' => 'berlin'], ['city' => $computed->rules()])->fails())->toBeTrue()
        // An absent parameter is a different matter and stays legal.
        ->and(validator([], ['city' => ['nullable', ...$computed->rules()]])->fails())->toBeFalse();
});
