<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\ValidationRuleBuilder;
use Ewk\Cms\Blocks\Tests\Fixtures\BuyAction;
use Ewk\Cms\Blocks\Tests\Fixtures\StaticLanguageCatalog;
use Illuminate\Support\Facades\Validator;

/**
 * @return array<string, list<mixed>>
 */
function textBlockRules(): array
{
    return app(ValidationRuleBuilder::class)->build((new TextBlock())->schema());
}

it('derives a rule for the dictionary and one per language of the catalog', function (): void {
    $rules = textBlockRules();

    expect(array_keys($rules))->toBe([
        'content.title',
        'content.title.ru',
        'content.title.en',
        'content.body',
        'content.body.ru',
        'content.body.en',
    ])
        ->and($rules['content.title'])->toBe(['sometimes', 'nullable', 'array'])
        ->and($rules['content.title.ru'])->toBe(['sometimes', 'nullable', 'string'])
        ->and($rules['content.body'])->toBe(['required', 'array'])
        ->and($rules['content.body.ru'])->toBe(['required', 'string'])
        ->and($rules['content.body.en'])->toBe(['sometimes', 'nullable', 'string']);
});

it('follows the language catalog it is given, not a hardcoded pair of languages', function (): void {
    $builder = new ValidationRuleBuilder(new StaticLanguageCatalog(['ru', 'en', 'de'], 'en'));

    $rules = $builder->build((new TextBlock())->schema());

    expect(array_keys($rules))->toBe([
        'content.title',
        'content.title.ru',
        'content.title.en',
        'content.title.de',
        'content.body',
        'content.body.ru',
        'content.body.en',
        'content.body.de',
    ])
        ->and($rules['content.body.en'])->toBe(['required', 'string'])
        ->and($rules['content.body.ru'])->toBe(['sometimes', 'nullable', 'string'])
        ->and($rules['content.body.de'])->toBe(['sometimes', 'nullable', 'string']);
});

it('requires a required field in the default language only, so a partial translation saves', function (): void {
    $validator = Validator::make(['content' => ['body' => ['en' => '']]], textBlockRules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('content.body.ru'))->toBeTrue()
        ->and($validator->errors()->has('content.body.en'))->toBeFalse();
});

it('accepts content translated into the default language alone', function (): void {
    $validator = Validator::make(['content' => ['body' => ['ru' => '<p>Текст</p>']]], textBlockRules());

    expect($validator->passes())->toBeTrue();
});

it('expands the rules of nested groups and repeated items', function (): void {
    $schema = BlockSchema::make()->fields([
        GroupField::make('cta')->fields([
            TextField::make('label')->translatable()->required(),
        ]),
        RepeaterField::make('slides')->min(1)->max(3)->fields([
            TextField::make('caption')->maxLength(80),
        ]),
    ]);

    $rules = app(ValidationRuleBuilder::class)->build($schema);

    expect(array_keys($rules))->toBe([
        'content.cta',
        'content.cta.label',
        'content.cta.label.ru',
        'content.cta.label.en',
        'content.slides',
        'content.slides.*.caption',
    ])
        ->and($rules['content.cta.label.ru'])->toBe(['required', 'string'])
        ->and($rules['content.slides'])->toBe(['sometimes', 'nullable', 'array', 'min:1', 'max:3'])
        ->and($rules['content.slides.*.caption'])->toBe(['sometimes', 'nullable', 'string', 'max:80']);
});

it('names every rule by the caption of its field, a language per translation and a wildcard per repeater', function (): void {
    $schema = BlockSchema::make()->fields([
        TextField::make('phone')->label('Телефон'),
        GroupField::make('cta')->label('Кнопка')->fields([
            TextField::make('label')->label('Подпись')->translatable()->required(),
        ]),
        RepeaterField::make('slides')->label('Слайды')->fields([
            TextField::make('caption'),
        ]),
    ]);

    $builder = app(ValidationRuleBuilder::class);
    $attributes = $builder->attributes($schema);

    // One caption per rule, so a refusal never falls back to the path.
    expect(array_keys($attributes))->toBe(array_keys($builder->build($schema)))
        ->and($attributes)->toBe([
            'content.phone' => 'Телефон',
            'content.cta' => 'Кнопка',
            'content.cta.label' => 'Подпись',
            'content.cta.label.ru' => 'Подпись (RU)',
            'content.cta.label.en' => 'Подпись (EN)',
            'content.slides' => 'Слайды',
            // A field without a label is named by its name.
            'content.slides.*.caption' => 'caption',
        ]);

    $validator = Validator::make(
        ['content' => ['cta' => ['label' => ['en' => 'Buy']]]],
        $builder->build($schema),
        [],
        $attributes,
    );

    expect($validator->errors()->first('content.cta.label.ru'))->toContain('Подпись (RU)')
        ->and($validator->errors()->first('content.cta.label.ru'))->not->toContain('content.');
});

it('names no language on an installation with one', function (): void {
    $builder = new ValidationRuleBuilder(new StaticLanguageCatalog(['ru'], 'ru'));

    $attributes = $builder->attributes(BlockSchema::make()->fields([
        TextField::make('title')->label('Заголовок')->translatable(),
    ]));

    expect($attributes)->toBe(['content.title' => 'Заголовок', 'content.title.ru' => 'Заголовок']);
});

it('rejects a javascript scheme and a schemeless host for a url link', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (string $url): bool => Validator::make(
        ['link' => ['kind' => 'url', 'attributes' => ['url' => $url]]],
        $rules,
    )->fails();

    expect($fails('javascript:alert(1)'))->toBeTrue()
        ->and($fails('//evil.host'))->toBeTrue()
        ->and($fails('https://example.com/tickets'))->toBeFalse()
        ->and($fails('http://example.com'))->toBeFalse();
});

it('rejects a link kind the schema does not allow', function (): void {
    $rules = ['link' => LinkField::make('cta')->allowedKinds(['entity', 'page', 'url'])->rules()];

    $validator = Validator::make(
        ['link' => ['kind' => 'action', 'attributes' => ['action' => 'buy']]],
        $rules,
    );

    expect($validator->fails())->toBeTrue()
        ->and(Validator::make(['link' => ['kind' => 'nonsense']], $rules)->fails())->toBeTrue();
});

it('checks mail and phone targets by their own format', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $value): bool => Validator::make(['link' => $value], $rules)->fails();

    expect($fails(['kind' => 'email', 'attributes' => ['email' => 'sales@example.com']]))->toBeFalse()
        ->and($fails(['kind' => 'email', 'attributes' => ['email' => 'not-an-email']]))->toBeTrue()
        ->and($fails(['kind' => 'phone', 'attributes' => ['phone' => '+420123456789']]))->toBeFalse()
        ->and($fails(['kind' => 'phone', 'attributes' => ['phone' => '12']]))->toBeTrue();
});

it('holds the address of a link to its ceiling and every other target to the short one', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $value): bool => Validator::make(['link' => $value], $rules)->fails();

    $longPath = str_repeat('a', LinkField::URL_LIMIT - strlen('https://example.com/'));

    expect($fails(['kind' => 'url', 'attributes' => ['url' => 'https://example.com/'.$longPath]]))->toBeFalse()
        ->and($fails(['kind' => 'url', 'attributes' => ['url' => 'https://example.com/'.$longPath.'a']]))->toBeTrue()
        ->and($fails(['kind' => 'anchor', 'attributes' => ['anchor' => str_repeat('a', LinkField::TARGET_LIMIT)]]))->toBeFalse()
        ->and($fails(['kind' => 'anchor', 'attributes' => ['anchor' => str_repeat('a', LinkField::TARGET_LIMIT + 1)]]))->toBeTrue();
});

it('refuses a link whose target is missing', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $value): bool => Validator::make(['link' => $value], $rules)->fails();

    // The kinds this package resolves on its own; `entity`, `page` and `list`
    // belong to the pages and are checked in their own suite.
    expect($fails(['kind' => 'anchor', 'attributes' => []]))->toBeTrue()
        ->and($fails(['kind' => 'anchor', 'attributes' => ['anchor' => 'raspisanie']]))->toBeFalse()
        ->and($fails(['kind' => 'file', 'attributes' => ['file' => '']]))->toBeTrue()
        ->and($fails(['kind' => 'file', 'attributes' => ['file' => '9f3c1a2b']]))->toBeFalse()
        ->and($fails(['kind' => 'url', 'attributes' => []]))->toBeTrue();
});

it('refuses a kind no resolver stands behind', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    // Pages belong to the pages part, which this suite does not load: storing
    // such a link would store an address nothing can build.
    expect(Validator::make(
        ['link' => ['kind' => 'page', 'attributes' => ['page' => '9f3c1a2b']]],
        $rules,
    )->fails())->toBeTrue();
});

it('refuses an action nothing registered and accepts a registered one', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (string $action): bool => Validator::make(
        ['link' => ['kind' => 'action', 'attributes' => ['action' => $action]]],
        $rules,
    )->fails();

    expect($fails('buy'))->toBeTrue();

    app(ActionRegistry::class)->register(new BuyAction());

    expect($fails('buy'))->toBeFalse()
        ->and($fails('tanzevat'))->toBeTrue();
});

it('stores nothing for a link whose target was left empty', function (): void {
    $field = LinkField::make('cta');

    expect($field->normalize(['kind' => 'url', 'attributes' => ['url' => ''], 'new_tab' => false]))->toBeNull()
        ->and($field->normalize(['kind' => 'anchor', 'attributes' => []]))->toBeNull()
        ->and($field->normalize(['kind' => 'url', 'attributes' => ['url' => 'https://example.com']]))->toBe([
            'kind' => 'url',
            'attributes' => ['url' => 'https://example.com'],
            'new_tab' => false,
        ]);
});
