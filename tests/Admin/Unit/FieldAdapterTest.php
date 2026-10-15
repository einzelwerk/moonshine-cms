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
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Media\MoonShine\Fields\MediaPicker;
use Ewk\MoonShineCms\Adapters\FieldAdapter;
use Ewk\MoonShineCms\MoonShine\Fields\EntityPicker;
use Ewk\MoonShineCms\MoonShine\Fields\LinkPicker;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\EveryFieldBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;
use Ewk\MoonShineCms\Tests\Fixtures\StaticLanguageCatalog;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Fields\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Fieldset;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

function adapter(?StaticLanguageCatalog $catalog = null): FieldAdapter
{
    return new FieldAdapter($catalog ?? new StaticLanguageCatalog(['de', 'fr'], 'de'));
}

it('maps every type of the core dictionary onto a moonshine component', function (): void {
    $adapter = adapter();

    expect($adapter->adapt(TextField::make('a')))->toBeInstanceOf(Text::class)
        ->and($adapter->adapt(RichTextField::make('b')))->toBeInstanceOf(Textarea::class)
        ->and($adapter->adapt(NumberField::make('c')))->toBeInstanceOf(Number::class)
        ->and($adapter->adapt(BooleanField::make('d')))->toBeInstanceOf(Switcher::class)
        ->and($adapter->adapt(DateField::make('e')))->toBeInstanceOf(Date::class)
        ->and($adapter->adapt(DateTimeField::make('f')))->toBeInstanceOf(Date::class)
        ->and($adapter->adapt(SelectField::make('g')->options(['x' => 'X', 'y' => 'Y'])))->toBeInstanceOf(Select::class)
        ->and($adapter->adapt(MediaField::make('i')))->toBeInstanceOf(MediaPicker::class)
        ->and($adapter->adapt(LinkField::make('j')))->toBeInstanceOf(LinkPicker::class)
        ->and($adapter->adapt(EntityField::make('k')->targets([Catalogue::class])))->toBeInstanceOf(EntityPicker::class)
        ->and($adapter->adapt(GroupField::make('l')->fields([TextField::make('m')])))->toBeInstanceOf(Fieldset::class)
        ->and($adapter->adapt(RepeaterField::make('n')->fields([TextField::make('o')])))->toBeInstanceOf(Json::class)
        ->and($adapter->adapt(ColorField::make('p')))->toBeInstanceOf(Color::class)
        ->and($adapter->adapt(CoordinatesField::make('q')))->toBeInstanceOf(Fieldset::class);
});

it('narrows a media picker to the kinds the field declares, pictures by default', function (): void {
    $default = adapter()->adapt(MediaField::make('image'));
    $video = adapter()->adapt(MediaField::make('video')->kinds(['video']));

    expect($default)->toBeInstanceOf(MediaPicker::class)
        ->and($default->getKinds())->toBe(['image', 'vector'])
        ->and($video->getKinds())->toBe(['video']);
});

it('renders a date time field with time and a plain date without it', function (): void {
    $adapter = adapter();

    $dateTime = $adapter->adapt(DateTimeField::make('starts_at'));
    $date = $adapter->adapt(DateField::make('starts_on'));

    expect($dateTime->getAttribute('type'))->toBe('datetime-local')
        ->and($date->getAttribute('type'))->toBe('date');
});

it('turns a multi select into one select that accepts many values', function (): void {
    $field = adapter()->adapt(MultiSelectField::make('tags')->options(['a' => 'A']));

    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->isMultiple())->toBeTrue();
});

it('names the column after the field and the language of a translatable one', function (): void {
    $adapter = adapter();
    $plain = TextField::make('title');
    $translated = TextField::make('title')->translatable();

    expect($adapter->columnOf($plain))->toBe('content.title')
        ->and($adapter->columnOf($translated, 'content', 'fr'))->toBe('content.title.fr')
        ->and($adapter->adapt($plain)->getNameAttribute())->toBe('content[title]')
        ->and($adapter->adapt($translated, 'content', 'fr')->getNameAttribute())->toBe('content[title][fr]');
});

it('carries label, required, help, default and the show condition', function (): void {
    $field = adapter()->adapt(
        TextField::make('venue')
            ->label('Площадка')
            ->required()
            ->help('Из справочника')
            ->default('Главная')
            ->visibleWhen('manual', true),
    );

    expect($field->getLabel())->toBe('Площадка')
        ->and($field->getAttribute('required'))->toBeTrue()
        ->and($field->getHint())->toBe('Из справочника')
        ->and($field->getDefault())->toBe('Главная')
        ->and($field->hasShowWhen())->toBeTrue()
        ->and($field->getShowWhenCondition()[0]['changeField'])->toBe('content[manual]')
        ->and($field->getShowWhenCondition()[0]['operator'])->toBe('=')
        ->and($field->getShowWhenCondition()[0]['value'])->toBeTrue();
});

it('carries the numeric bounds of a number field', function (): void {
    $whole = adapter()->adapt(NumberField::make('weight')->min(1)->max(9)->integer());
    $fraction = adapter()->adapt(NumberField::make('ratio')->integer(false));

    expect($whole->getAttribute('min'))->toBe('1')
        ->and($whole->getAttribute('max'))->toBe('9')
        ->and($whole->getAttribute('step'))->toBe('1')
        ->and($fraction->getAttribute('step'))->toBe('any');
});

it('builds one language tab per locale of the injected catalog', function (): void {
    $schema = BlockSchema::make()->fields([
        TextField::make('title')->translatable(),
        TextField::make('code'),
    ]);

    $components = adapter(new StaticLanguageCatalog(['de', 'fr', 'it'], 'fr'))->form($schema);

    $tabs = array_values(array_filter($components, static fn (object $c): bool => $c instanceof Tabs));

    expect($components[0])->toBeInstanceOf(Text::class)
        ->and($tabs)->toHaveCount(1);

    $titles = array_map(
        static fn (object $tab): string => (string) $tab->getLabel(),
        iterator_to_array($tabs[0]->getComponents()),
    );

    expect($titles)->toBe(['DE', 'FR', 'IT']);
});

it('puts the fields of one declared group into one fieldset', function (): void {
    $schema = BlockSchema::make()->fields([
        ColorField::make('accent')->group('Оформление'),
        CoordinatesField::make('point')->group('Оформление'),
        TextField::make('loose'),
    ]);

    $components = adapter()->form($schema);

    expect($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(Fieldset::class)
        ->and($components[0]->getLabel())->toBe('Оформление')
        ->and($components[1])->toBeInstanceOf(Text::class);
});

it('nests the fields of a repeater under bare names', function (): void {
    $repeater = adapter()->adapt(
        RepeaterField::make('items')->fields([TextField::make('label')]),
    );

    $nested = iterator_to_array($repeater->getFields());

    expect($repeater->getColumn())->toBe('content.items')
        ->and($nested[0]->getColumn())->toBe('label');
});

it('keeps a repeater of plain fields in table mode', function (): void {
    $repeater = adapter()->adapt(
        RepeaterField::make('items')->fields([TextField::make('label'), NumberField::make('order')]),
    );

    expect($repeater->isVertical())->toBeFalse();
});

it('switches a repeater to vertical mode when a row cannot fit a table cell', function (): void {
    // A table row is one `<td>` per field: a plain input fits it, a
    // composite field — link, media, group — renders its own label and
    // several inputs of its own and breaks the row's grid instead of
    // sitting inside a cell, see the moonshine-cms bug this pins.
    $withMedia = adapter()->adapt(
        RepeaterField::make('slides')->fields([TextField::make('title'), MediaField::make('image')]),
    );
    $withLink = adapter()->adapt(
        RepeaterField::make('chips')->fields([TextField::make('label'), LinkField::make('link')]),
    );
    $withGroup = adapter()->adapt(
        RepeaterField::make('rows')->fields([GroupField::make('pair')->fields([TextField::make('a')])]),
    );

    expect($withMedia->isVertical())->toBeTrue()
        ->and($withLink->isVertical())->toBeTrue()
        ->and($withGroup->isVertical())->toBeTrue();
});

it('switches a repeater to vertical mode when a row carries a language copy', function (): void {
    // A table hands every field one column of the row and one heading for the
    // whole column: the languages of one caption would stand side by side
    // under two identical headings, and a heading cannot be hidden per row.
    $catalog = new StaticLanguageCatalog(['de', 'fr'], 'de');
    $row = RepeaterField::make('items')->fields([TextField::make('label')->translatable()]);

    expect(adapter($catalog)->adapt($row)->isVertical())->toBeFalse()
        ->and(adapter($catalog)->withLocaleSwitcher()->adapt($row)->isVertical())->toBeTrue();
});

it('reads the language of an input off the input and never out of a quote', function (): void {
    // The mark of a wrapper travels through MoonShine twice inside a repeater
    // row, once as the wrapper of the field and once as the cell of the row,
    // and is escaped twice on the way: a quoted language arrives as
    // `locale === &#039;fr&#039;` and stops being JavaScript, and an `&&`
    // arrives as `&amp;&amp;` with the same result. The guard that keeps the
    // expression out of the console when the switch is out of scope is
    // therefore a ternary over a `typeof` and carries neither.
    $components = adapter(new StaticLanguageCatalog(['de', 'fr'], 'de'))
        ->withLocaleSwitcher()
        ->form(BlockSchema::make()->fields([TextField::make('title')->translatable()]));

    $attributes = (string) $components[0]->getWrapperAttributes();

    expect($attributes)
        ->toContain('x-show="typeof locale === typeof undefined ? false : locale === $el.dataset.cmsLocale"')
        ->and($attributes)->not->toContain('&#039;')
        ->and($attributes)->not->toContain('&amp;');
});

it('spells a translatable field inside a repeater out as one flat column per locale', function (): void {
    $repeater = adapter(new StaticLanguageCatalog(['de', 'fr'], 'de'))->adapt(
        RepeaterField::make('slides')->fields([
            TextField::make('title')->translatable(),
            TextField::make('code'),
        ]),
    );

    $nested = iterator_to_array($repeater->getFields());

    // A row of a Json repeater renders as a table of plain fields and
    // rejects a Tabs component, so a translatable field cannot fold into a
    // tab the way the outer form does — and a value such as
    // ['de' => ..., 'fr' => ...] would still crash a single plain input.
    // It must come out as one flat, locale-suffixed field per language, in
    // the place the declaration gave it.
    expect($nested)->toHaveCount(3)
        ->and($nested[0])->toBeInstanceOf(Text::class)
        ->and($nested[0]->getColumn())->toBe('title.de')
        ->and($nested[0]->getLabel())->toContain('DE')
        ->and($nested[1]->getColumn())->toBe('title.fr')
        ->and($nested[1]->getLabel())->toContain('FR')
        ->and($nested[2]->getColumn())->toBe('code');
});

it('offers the link kinds a field allows and every kind when it narrows nothing', function (): void {
    $picker = adapter()->adapt(LinkField::make('cta')->allowedKinds(['url', 'email']));

    expect($picker)->toBeInstanceOf(LinkPicker::class)
        ->and(array_map(
            static fn (LinkKind $kind): string => $kind->value,
            $picker->getKinds(),
        ))->toBe(['url', 'email'])
        // A link field that narrows nothing offers every kind, in the order
        // of the enum.
        ->and(adapter()->adapt(LinkField::make('cta'))->getKinds())->toBe(LinkKind::cases());
});

it('names the inputs of a composite field after the field itself', function (): void {
    // Inside a repeater row the name carries the placeholder of the row, and
    // the sub inputs have to inherit it or the second row overwrites the first.
    $link = adapter()->adapt(LinkField::make('cta'));
    $media = adapter()->adapt(MediaField::make('image'));

    expect((string) $link->inputAttributes('[kind]'))->toContain('name="content[cta][kind]"')
        ->and((string) $link->inputAttributes('[attributes][url]'))->toContain('name="content[cta][attributes][url]"')
        ->and((string) $media->inputAttributes('[file]'))->toContain('name="content[image][file]"');
});

it('builds the form of the whole schema without unsupported types', function (): void {
    $components = adapter()->form((new EveryFieldBlock())->schema());

    expect($components)->not->toBeEmpty();
});

it('keeps every field of the schema in place when the form carries a language switch', function (): void {
    $schema = BlockSchema::make()->fields([
        TextField::make('title')->translatable(),
        TextField::make('code'),
    ]);

    $components = adapter(new StaticLanguageCatalog(['de', 'fr'], 'de'))
        ->withLocaleSwitcher()
        ->form($schema);

    // No tab strip at the end: one input per language in the place the
    // declaration gave the field.
    expect($components)->toHaveCount(3)
        ->and(array_filter($components, static fn (object $c): bool => $c instanceof Tabs))->toBe([])
        ->and($components[0]->getColumn())->toBe('content.title.de')
        ->and($components[1]->getColumn())->toBe('content.title.fr')
        ->and($components[2]->getColumn())->toBe('content.code');
});

it('marks a translatable input with its language and hides all but the default one', function (): void {
    $schema = BlockSchema::make()->fields([TextField::make('title')->translatable()]);

    $components = adapter(new StaticLanguageCatalog(['de', 'fr'], 'de'))
        ->withLocaleSwitcher()
        ->form($schema);

    $default = (string) $components[0]->getWrapperAttributes();
    $other = (string) $components[1]->getWrapperAttributes();

    expect($default)->toContain('data-cms-locale="de"')
        ->and($default)->not->toContain('display:none')
        ->and($other)->toContain('data-cms-locale="fr"')
        ->and($other)->toContain('display:none');
});

it('requires a translatable field in the default language only', function (): void {
    $schema = BlockSchema::make()->fields([
        TextField::make('title')->translatable()->required(),
    ]);

    $components = adapter(new StaticLanguageCatalog(['de', 'fr'], 'de'))
        ->withLocaleSwitcher()
        ->form($schema);

    // Publication rolls out every language and an empty one falls back — and a
    // hidden required input makes the browser refuse to submit the card at all.
    expect($components[0]->getAttribute('required'))->toBeTrue()
        ->and($components[1]->getAttribute('required'))->toBeFalse();
});
