<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\ValidationRuleBuilder;
use Ewk\Cms\Blocks\Tests\Fixtures\BlockOwner;
use Ewk\Cms\Blocks\Tests\Fixtures\OnlyManualOptions;
use Ewk\Cms\Blocks\Tests\Fixtures\SourceOptions;

function sourceField(): SelectField
{
    return SelectField::make('source')
        ->options(['subject' => 'Из сущности страницы', 'manual' => 'Вручную'])
        ->optionsFor(SourceOptions::class);
}

it('offers the values the owner allows and the declared ones without a context', function (): void {
    $field = sourceField();

    $withSubject = FieldContext::of(new BlockOwner(), BlockOwner::class);
    $withoutSubject = FieldContext::of(new BlockOwner());

    expect(array_keys($field->optionsIn($withSubject)))->toBe(['subject', 'manual'])
        ->and(array_keys($field->optionsIn($withoutSubject)))->toBe(['manual'])
        // A caller outside a form is handed the declaration itself: a rule
        // derived without a context must never be stricter than the form.
        ->and(array_keys($field->optionsIn(FieldContext::none())))->toBe(['subject', 'manual'])
        ->and(array_keys($field->getOptions()))->toBe(['subject', 'manual'])
        ->and($field->getOptionsProvider())->toBe(SourceOptions::class);
});

it('describes the owner and the entity it is rendered for', function (): void {
    $owner = new BlockOwner();

    $context = FieldContext::of($owner, BlockOwner::class);

    expect($context->owner)->toBe($owner)
        ->and($context->hasSubject())->toBeTrue()
        ->and($context->subjectIs(BlockOwner::class))->toBeTrue()
        ->and($context->subjectIs(SelectField::class))->toBeFalse()
        ->and(FieldContext::none()->hasSubject())->toBeFalse()
        ->and(FieldContext::none()->owner)->toBeNull();
});

it('keeps the declared options for a field that names no provider', function (): void {
    $field = SelectField::make('align')->options(['left' => 'Слева', 'right' => 'Справа']);

    expect($field->optionsIn(FieldContext::of(new BlockOwner())))->toBe(['left' => 'Слева', 'right' => 'Справа']);
});

it('refuses on save a value the owner does not offer', function (): void {
    $field = sourceField();

    expect($field->rulesIn(FieldContext::of(new BlockOwner(), BlockOwner::class)))
        ->toBe(['string', 'in:subject,manual'])
        ->and($field->rulesIn(FieldContext::of(new BlockOwner())))->toBe(['string', 'in:manual'])
        // The rules of a field asked without a context stay the declared ones.
        ->and($field->rules())->toBe(['string', 'in:subject,manual']);
});

it('narrows the rules of a whole schema by the owner of the block', function (): void {
    $schema = BlockSchema::make()
        ->title('Подборка')
        ->fields([TextField::make('title'), sourceField()]);

    $builder = app(ValidationRuleBuilder::class);

    expect($builder->build($schema, FieldContext::of(new BlockOwner()))['content.source'])
        ->toBe(['sometimes', 'nullable', 'string', 'in:manual'])
        ->and($builder->build($schema, FieldContext::of(new BlockOwner(), BlockOwner::class))['content.source'])
        ->toBe(['sometimes', 'nullable', 'string', 'in:subject,manual'])
        ->and($builder->build($schema)['content.source'])
        ->toBe(['sometimes', 'nullable', 'string', 'in:subject,manual']);
});

it('falls back to the declared options when the provider cannot be built', function (): void {
    /** @var class-string<\Ewk\Cms\Blocks\Fields\FieldOptionsProvider> $missing */
    $missing = 'Ewk\Cms\Blocks\Tests\Fixtures\NoSuchOptionsProvider';

    $field = SelectField::make('source')
        ->options(['manual' => 'Вручную'])
        ->optionsFor($missing);

    expect($field->optionsIn(FieldContext::none()))->toBe(['manual' => 'Вручную']);
});

it('settles a field whose provider leaves one value on offer', function (): void {
    $field = SelectField::make('source')
        ->options(['subject' => 'Из сущности страницы', 'manual' => 'Вручную'])
        ->optionsFor(OnlyManualOptions::class);

    expect($field->optionsIn(FieldContext::of(new BlockOwner(), BlockOwner::class)))
        ->toBe(['manual' => 'Вручную']);
});
