<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Fields\ColorField;
use Ewk\Cms\Blocks\Fields\CoordinatesField;
use Ewk\Cms\Blocks\Fields\DateField;
use Ewk\Cms\Blocks\Fields\DateTimeField;
use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\MultiSelectField;
use Ewk\Cms\Blocks\Fields\NumberField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Fields\TextareaField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\Resolvers\UrlLinkResolver;

it('normalizes a translatable text value per language and a shared one as a string', function (): void {
    $translatable = TextField::make('title')->translatable();
    $shared = TextField::make('code');

    expect($translatable->normalize(['ru' => '  Заголовок  ', 'en' => '']))
        ->toBe(['ru' => 'Заголовок', 'en' => null])
        ->and($shared->normalize('  plain  '))->toBe('plain')
        ->and($shared->normalize([]))->toBeNull();
});

it('carries the declared length limit into the text rules', function (): void {
    expect(TextField::make('title')->rules())->toBe(['string'])
        ->and(TextField::make('title')->maxLength(120)->rules())->toBe(['string', 'max:120'])
        ->and(TextareaField::make('lead')->maxLength(500)->rules())->toBe(['string', 'max:500']);
});

it('strips active markup and event handlers out of rich text payload', function (): void {
    $field = RichTextField::make('body');

    $payload = $field->toPayload(
        '<p onclick="steal()">Текст <a href="javascript:alert(1)">ссылка</a></p><script>alert(1)</script>',
        'ru',
        'ru',
    );

    expect($payload)->toBe('<p>Текст <a>ссылка</a></p>')
        ->and($field->sanitizes())->toBeTrue();
});

it('keeps rich text untouched when sanitizing is switched off deliberately', function (): void {
    $field = RichTextField::make('body')->sanitize(false);

    expect($field->toPayload('<p onclick="x()">Текст</p>', 'ru', 'ru'))->toBe('<p onclick="x()">Текст</p>')
        ->and($field->sanitizes())->toBeFalse();
});

it('casts numbers to whole values unless floats are allowed', function (): void {
    $whole = NumberField::make('columns')->min(1)->max(4);
    $float = NumberField::make('ratio')->integer(false);

    expect($whole->normalize('3.7'))->toBe(3)
        ->and($whole->normalize(''))->toBeNull()
        ->and($whole->rules())->toBe(['numeric', 'integer', 'min:1', 'max:4'])
        ->and($float->normalize('3.5'))->toBe(3.5)
        ->and($float->rules())->toBe(['numeric']);
});

it('casts boolean-ish input to booleans', function (): void {
    $field = BooleanField::make('wide');

    expect($field->normalize('0'))->toBeFalse()
        ->and($field->normalize('true'))->toBeTrue()
        ->and($field->normalize(null))->toBeNull()
        ->and($field->rules())->toBe(['boolean']);
});

it('validates dates by their stored iso shape', function (): void {
    expect(DateField::make('starts_on')->rules())->toBe(['date_format:Y-m-d'])
        ->and(DateTimeField::make('starts_at')->rules())->toBe(['date'])
        ->and(DateField::make('starts_on')->normalize('2026-09-13'))->toBe('2026-09-13');
});

it('limits a select to the declared options', function (): void {
    $single = SelectField::make('align')->options(['left' => 'Слева', 'right' => 'Справа']);

    expect($single->rules())->toBe(['string', 'in:left,right'])
        ->and($single->getOptions())->toBe(['left' => 'Слева', 'right' => 'Справа'])
        ->and($single->normalize(' left '))->toBe('left');
});

it('normalizes a multi select to a unique list of declared values', function (): void {
    $field = MultiSelectField::make('tags')->options(['a' => 'A', 'b' => 'B']);

    expect($field->normalize(['a', 'a', 'b', '']))->toBe(['a', 'b'])
        ->and($field->normalize('a'))->toBeNull();
});

it('keeps a media reference as a uuid and metadata, unresolved', function (): void {
    $field = MediaField::make('image');
    $reference = ['id' => '9f3c1a2b-4e55-4c1e-9a3f-0b71d2c35e10', 'metadata' => ['alt' => ['ru' => 'Сцена']]];

    expect($field->normalize($reference))->toBe($reference)
        ->and($field->toPayload($reference, 'ru', 'ru'))->toBe($reference)
        ->and($field->normalize(['metadata' => []]))->toBeNull();
});

it('declares the kinds of file a media field takes, none by default', function (): void {
    expect(MediaField::make('image')->getKinds())->toBe([])
        ->and(MediaField::make('video')->kinds(['video', 'video'])->getKinds())->toBe(['video']);
});

it('resolves a link payload through the registry and keeps the kind without one', function (): void {
    $field = LinkField::make('cta')->allowedKinds(['url']);
    $stored = ['kind' => 'url', 'attributes' => ['url' => 'https://example.com'], 'new_tab' => true];

    $resolvers = new LinkResolverRegistry();
    $resolvers->register(new UrlLinkResolver());

    expect($field->toPayload($stored, 'ru', 'ru', $resolvers))->toBe([
        'kind' => 'url',
        'href' => 'https://example.com',
        'external' => true,
        'new_tab' => true,
        'action' => null,
    ])
        ->and($field->toPayload($stored, 'ru', 'ru'))->toBe([
            'kind' => 'url',
            'href' => null,
            'external' => false,
            'new_tab' => true,
            'action' => null,
        ])
        ->and($field->toPayload(null, 'ru', 'ru', $resolvers))->toBeNull()
        ->and($field->getAllowedKinds())->toBe(['url']);
});

it('normalizes entity references by type and id', function (): void {
    $single = EntityField::make('event')->targets(['event']);
    $many = EntityField::make('events')->targets(['event'])->multiple();

    expect($single->normalize(['type' => 'event', 'id' => 12]))->toBe(['type' => 'event', 'id' => 12])
        ->and($single->normalize(['type' => 'event']))->toBeNull()
        ->and($many->normalize([['type' => 'event', 'id' => 1], ['id' => 2]]))
        ->toBe([['type' => 'event', 'id' => 1]])
        ->and($many->isMultiple())->toBeTrue()
        ->and($many->getTargets())->toBe(['event']);
});

it('builds nested payloads for groups and repeaters', function (): void {
    $group = GroupField::make('cta')->fields([
        TextField::make('label')->translatable(),
        NumberField::make('weight'),
    ]);

    $repeater = RepeaterField::make('slides')->min(1)->max(3)->fields([
        TextField::make('caption')->translatable(),
    ]);

    expect($group->toPayload(['label' => ['ru' => 'Купить'], 'weight' => '2'], 'ru', 'ru'))
        ->toBe(['label' => 'Купить', 'weight' => 2])
        ->and($repeater->toPayload([['caption' => ['en' => 'First']], []], 'en', 'ru'))
        ->toBe([['caption' => 'First'], ['caption' => '']])
        ->and($repeater->rules())->toBe(['array', 'min:1', 'max:3'])
        ->and($repeater->toPayload(null, 'ru', 'ru'))->toBe([]);
});

it('stores colors as hex and coordinates as a float pair', function (): void {
    expect(ColorField::make('accent')->normalize(' #1F2937 '))->toBe('#1F2937')
        ->and(CoordinatesField::make('point')->normalize(['lat' => '50.08', 'lng' => '14.44']))
        ->toBe(['lat' => 50.08, 'lng' => 14.44])
        ->and(CoordinatesField::make('point')->normalize(['lat' => 'x', 'lng' => 1]))->toBeNull();
});
