<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\ValidationRuleBuilder;
use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchema;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Tests\Fixtures\TestChromeSchemas;

it('finds a declared field by name and reports an undeclared one', function (): void {
    $schema = TestChromeSchemas::footer();

    expect($schema->field('copyright'))->toBeInstanceOf(TextField::class)
        ->and($schema->hasField('copyright'))->toBeTrue()
        ->and($schema->hasField('tracking_id'))->toBeFalse()
        ->and($schema->field('tracking_id'))->toBeNull();
});

it('derives the validation rules of a footer through the rule builder of the blocks package', function (): void {
    $rules = app(ValidationRuleBuilder::class)->build(TestChromeSchemas::footer()->toBlockSchema());

    // The translatable required field demands the default language only, and
    // the rules are keyed by the `content` column — the same derivation a
    // block gets, with no second implementation here.
    expect($rules['content.copyright'])->toBe(['required', 'array'])
        ->and($rules['content.copyright.ru'])->toBe(['required', 'string'])
        ->and($rules['content.copyright.en'])->toBe(['sometimes', 'nullable', 'string'])
        ->and($rules)->toHaveKey('content.brand_link');
});

it('hands out an empty schema for a kind the installation has not declared', function (): void {
    $registry = app(ChromeSchemaRegistry::class);

    expect($registry->has(ChromeKind::HEADER))->toBeFalse()
        ->and($registry->schemaFor(ChromeKind::HEADER)->fields())->toBe([]);

    $registry->register(ChromeKind::HEADER, ChromeSchema::make()->title('Header'));

    expect($registry->has(ChromeKind::HEADER))->toBeTrue()
        ->and($registry->schemaFor(ChromeKind::HEADER)->title())->toBe('Header');
});
