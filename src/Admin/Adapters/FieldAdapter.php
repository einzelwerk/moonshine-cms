<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Adapters;

use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Fields\ColorField;
use Ewk\Cms\Blocks\Fields\CoordinatesField;
use Ewk\Cms\Blocks\Fields\DateField;
use Ewk\Cms\Blocks\Fields\DateTimeField;
use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\FieldContext;
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
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\MoonShine\Fields\CompositeValueField;
use Ewk\Cms\Media\MoonShine\Fields\MediaPicker;
use Ewk\MoonShineCms\Editor\EditorAssets;
use Ewk\MoonShineCms\Exceptions\UnsupportedFieldTypeException;
use Ewk\MoonShineCms\Links\LinkEndpoints;
use Ewk\MoonShineCms\MoonShine\Fields\EntityPicker;
use Ewk\MoonShineCms\MoonShine\Fields\LinkPicker;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\TableBuilderContract;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Contracts\HasDefaultValueContract;
use MoonShine\UI\Fields\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Field as MoonShineField;
use MoonShine\UI\Fields\Fieldset;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * The one place where the neutral field dictionary of the core
 * (`Ewk\Cms\Blocks\Fields\*`) becomes real MoonShine components.
 *
 * The core declaration stays free of admin classes; everything MoonShine
 * specific lives here, so a second interface would replace this class and
 * nothing else.
 *
 * A translatable field is rendered as one tab per language of the injected
 * {@see LanguageCatalog} and never as one input per language — unless the form
 * carries a language switcher of its own, see {@see self::withLocaleSwitcher()}.
 */
final readonly class FieldAdapter
{
    /**
     * The head of the condition every language mark is shown by. `typeof`
     * asks whether the switch of the editor is in scope at all: a row of a
     * repeater is built by the panel outside it, and a bare name would throw
     * there instead of simply staying hidden. The literal `undefined` is
     * spelled as a `typeof` of its own and the guard is a ternary and not an
     * `&&`: an attribute that travels through the panel twice comes back
     * escaped, and neither a quote nor an ampersand survives that as
     * JavaScript.
     */
    private const string LOCALE_SHOWN = 'typeof locale === typeof undefined ? false : locale === ';

    /**
     * @param  class-string<MoonShineField>  $richTextField  MoonShine 4.19 ships no rich
     *                                                       text editor, so the class is configurable
     */
    public function __construct(
        private LanguageCatalog $catalog,
        private string $richTextField = Textarea::class,
        private int $entityOptionsLimit = 100,
        private bool $localeSwitcher = false,
    ) {
    }

    /**
     * The same adapter for a form that carries one language switcher of its
     * own. Every translatable field is then drawn in place, once per language,
     * and only the chosen one is visible; the fields keep the order of the
     * declaration instead of the translatable ones sinking into tabs at the
     * end.
     *
     * The visibility reads `locale` from the surrounding Alpine scope, so a
     * form without such a scope keeps the language tabs.
     */
    public function withLocaleSwitcher(): self
    {
        return new self($this->catalog, $this->richTextField, $this->entityOptionsLimit, true);
    }

    /**
     * The form of one block, in the order the schema declares its fields:
     * groups become fieldsets in place, declared tabs a tab strip in the place
     * of their first field, and a translatable field either one input per
     * language in place or, without a switcher, a language tab strip at the
     * end.
     *
     * The context says who the block belongs to, and a field whose values
     * depend on that owner is drawn accordingly: with the values it offers
     * here, or not at all when it offers exactly one.
     *
     * @return list<ComponentContract>
     */
    public function form(BlockSchema $schema, string $prefix = 'content', ?FieldContext $context = null): array
    {
        return $this->fieldsOf($schema->fields(), $prefix, context: $context ?? FieldContext::none());
    }

    /**
     * Shared by the top-level block form and every nested field group (a
     * {@see GroupField} fieldset or one row of a {@see RepeaterField}).
     *
     * A {@see Tabs} component is not a {@see \MoonShine\Contracts\UI\FieldContract}:
     * `MoonShine\UI\Fields\Json` draws every repeater row as one table row of
     * plain fields and rejects anything else, so `$flat` asks for translatable
     * and declared-tab fields spelled out as one flat, locale- or
     * tab-suffixed field each instead of grouped under a `Tabs` wrapper. A
     * value such as `['ru' => ..., 'en' => ...]` can never be handed to a
     * single plain input either way — a repeater row just cannot fold that
     * choice into a tab the way the outer form does.
     *
     * @param  list<Field>  $fields
     * @return list<ComponentContract>
     */
    private function fieldsOf(array $fields, string $prefix, bool $flat = false, ?FieldContext $context = null): array
    {
        $context ??= FieldContext::none();

        if ($flat) {
            return $this->flatFieldsOf($fields, $prefix, $context);
        }

        $tabbed = array_values(array_filter($fields, static fn (Field $field): bool => $field->getTab() !== null));
        $plain = array_values(array_filter($fields, static fn (Field $field): bool => $field->getTab() === null));

        // Without a switcher the translatable fields are a language tab strip
        // of their own at the end, so they leave the ordered walk here.
        $deferred = [];

        if (! $this->localeSwitcher) {
            $deferred = array_values(array_filter($plain, static fn (Field $field): bool => $field->isTranslatable()));
            $plain = array_values(array_filter($plain, static fn (Field $field): bool => ! $field->isTranslatable()));
            $tabbed = array_values(array_filter($tabbed, static fn (Field $field): bool => ! $field->isTranslatable()));
        }

        /** @var list<ComponentContract> $components */
        $components = $this->grouped($plain, $prefix, context: $context);

        if ($tabbed !== []) {
            $components[] = $this->declaredTabs($tabbed, $prefix, $context);
        }

        if ($deferred !== []) {
            $components[] = $this->languageTabs($deferred, $prefix, $context);
        }

        return $components;
    }

    /**
     * One row of a repeater: no tab strip of any kind, so a declared tab
     * becomes a label prefix and every language an input of its own.
     *
     * @param  list<Field>  $fields
     * @return list<ComponentContract>
     */
    private function flatFieldsOf(array $fields, string $prefix, FieldContext $context): array
    {
        $plain = array_values(array_filter($fields, static fn (Field $field): bool => $field->getTab() === null));
        $tabbed = array_values(array_filter($fields, static fn (Field $field): bool => $field->getTab() !== null));

        /** @var list<ComponentContract> $components */
        $components = [];

        foreach ($plain as $field) {
            array_push($components, ...($field->isTranslatable()
                ? $this->flatLocales([$field], $prefix, $context)
                : [$this->adapt($field, $prefix, null, $context)]));
        }

        if ($tabbed !== []) {
            array_push($components, ...$this->flatTabbed($tabbed, $prefix, $context));
        }

        return $components;
    }

    /**
     * The flat equivalent of {@see self::declaredTabs()}: one field per
     * declared tab, labelled with the tab it came from since there is no tab
     * strip left to carry that context.
     *
     * @param  list<Field>  $fields
     * @return list<MoonShineField>
     */
    private function flatTabbed(array $fields, string $prefix, FieldContext $context): array
    {
        $components = [];

        foreach ($fields as $field) {
            $label = $field->getTab().': '.($field->getLabel() ?? $field->name());
            $components[] = $this->carry($field, $this->component($field, $label, $this->columnOf($field, $prefix), $context), $prefix);
        }

        return $components;
    }

    /**
     * The flat equivalent of {@see self::languageTabs()}: one field per
     * language of every translatable field, labelled with its locale since
     * there is no tab strip left to carry that context.
     *
     * @param  list<Field>  $fields
     * @return list<MoonShineField>
     */
    private function flatLocales(array $fields, string $prefix, FieldContext $context): array
    {
        $components = [];

        foreach ($fields as $field) {
            foreach ($this->catalog->locales() as $locale) {
                // With a switcher the language is a mark on the field and not
                // part of its caption: the caption then reads the same in
                // every language.
                $label = $this->localeSwitcher
                    ? ($field->getLabel() ?? $field->name())
                    : ($field->getLabel() ?? $field->name()).' ('.mb_strtoupper($locale).')';

                $column = $this->columnOf($field, $prefix, $locale);
                $components[] = $this->markLocale(
                    $this->carry($field, $this->component($field, $label, $column, $context), $prefix),
                    $locale,
                );
            }
        }

        return $components;
    }

    /**
     * The language of one input, as the switcher of the editor reads it: the
     * mark is on the wrapper, so the badge and the visibility need no second
     * element of their own. Every language but the default one starts hidden,
     * so nothing flashes before Alpine takes over.
     */
    private function markLocale(MoonShineField $component, string $locale): MoonShineField
    {
        if (! $this->localeSwitcher) {
            return $component;
        }

        $attributes = [
            'data-cms-locale' => $locale,
            // The condition names no language of its own: an attribute that
            // travels through MoonShine twice, once as the wrapper of the
            // field and once as the cell of a repeater row, is escaped twice,
            // and `locale === &#039;ru&#039;` is not JavaScript any more. The
            // element reads the language off the mark it already carries.
            //
            // The switch is asked for by `typeof` because a row of a repeater
            // is built by the panel on its own, outside the editor, and an
            // expression that names a variable of a scope it has not got yet
            // throws into the console on every load.
            'x-show' => self::LOCALE_SHOWN.'$el.dataset.cmsLocale',
        ];

        if ($locale !== $this->catalog->defaultLocale()) {
            $attributes['style'] = 'display:none';

            // «Required» belongs to the language of the panel's default and to
            // it alone: publication rolls out every language and an empty one
            // falls back. A required input that is hidden would also make the
            // browser refuse to submit the card at all, without being able to
            // show what is wrong.
            $component = $component->required(false);
        }

        return $component->customWrapperAttributes($attributes);
    }

    /**
     * One core field as one MoonShine component. A composite value (a link, a
     * pair of coordinates, a group) becomes a fieldset of inputs, which is
     * still one component to the form.
     */
    public function adapt(
        Field $field,
        string $prefix = 'content',
        ?string $locale = null,
        ?FieldContext $context = null,
    ): MoonShineField {
        $column = $this->columnOf($field, $prefix, $locale);
        $label = $field->getLabel() ?? $field->name();

        return $this->carry(
            $field,
            $this->component($field, $label, $column, $context ?? FieldContext::none()),
            $prefix,
        );
    }

    /**
     * The MoonShine column of a field: translatable values live under their
     * language, everything else directly under the block content.
     */
    public function columnOf(Field $field, string $prefix = 'content', ?string $locale = null): string
    {
        $column = $prefix === '' ? $field->name() : $prefix.'.'.$field->name();

        return $field->isTranslatable() && $locale !== null ? $column.'.'.$locale : $column;
    }

    /**
     * @param  list<Field>  $fields
     * @return list<MoonShineField>
     */
    private function grouped(array $fields, string $prefix, ?string $locale = null, ?FieldContext $context = null): array
    {
        /** @var array<string, list<MoonShineField>> $groups */
        $groups = [];
        /** @var list<MoonShineField|string> $order */
        $order = [];

        foreach ($fields as $field) {
            $group = $field->getGroup();
            $adapted = $this->inPlace($field, $prefix, $locale, $context);

            if ($group === null) {
                array_push($order, ...$adapted);

                continue;
            }

            if (! array_key_exists($group, $groups)) {
                $groups[$group] = [];
                $order[] = $group;
            }

            array_push($groups[$group], ...$adapted);
        }

        $components = [];

        foreach ($order as $item) {
            $components[] = is_string($item) ? Fieldset::make($item, $groups[$item]) : $item;
        }

        return $components;
    }

    /**
     * One declared field as the components it takes in the form: one per
     * language when a switcher shows the languages in place, one otherwise.
     *
     * @return list<MoonShineField>
     */
    private function inPlace(Field $field, string $prefix, ?string $locale, ?FieldContext $context): array
    {
        $context ??= FieldContext::none();

        if (! $this->localeSwitcher || ! $field->isTranslatable() || $locale !== null) {
            return [$this->adapt($field, $prefix, $locale, $context)];
        }

        $components = [];

        foreach ($this->catalog->locales() as $candidate) {
            $components[] = $this->markLocale(
                $this->adapt($field, $prefix, $candidate, $context),
                $candidate,
            );
        }

        return $components;
    }

    /**
     * @param  list<Field>  $fields
     */
    private function declaredTabs(array $fields, string $prefix, FieldContext $context): Tabs
    {
        /** @var array<string, list<Field>> $byTab */
        $byTab = [];

        foreach ($fields as $field) {
            $byTab[(string) $field->getTab()][] = $field;
        }

        $tabs = [];

        foreach ($byTab as $title => $tabFields) {
            $tabs[] = Tab::make($title, $this->grouped($tabFields, $prefix, context: $context));
        }

        return Tabs::make($tabs);
    }

    /**
     * @param  list<Field>  $fields
     */
    private function languageTabs(array $fields, string $prefix, FieldContext $context): Tabs
    {
        $tabs = [];

        foreach ($this->catalog->locales() as $locale) {
            $tabs[] = Tab::make(mb_strtoupper($locale), $this->grouped($fields, $prefix, $locale, $context))
                ->active($locale === $this->catalog->defaultLocale());
        }

        return Tabs::make($tabs);
    }

    private function component(Field $field, string $label, string $column, FieldContext $context): MoonShineField
    {
        return match (true) {
            $field instanceof RichTextField => $this->richText($label, $column),
            $field instanceof TextField => $this->limited(Text::make($label, $column), $field->getMaxLength()),
            $field instanceof TextareaField => $this->limited(Textarea::make($label, $column), $field->getMaxLength()),
            $field instanceof NumberField => $this->number($field, $label, $column),
            $field instanceof BooleanField => Switcher::make($label, $column),
            $field instanceof DateTimeField => Date::make($label, $column)->withTime(),
            $field instanceof DateField => Date::make($label, $column),
            $field instanceof MultiSelectField => Select::make($label, $column)
                ->options($field->getOptions())
                ->multiple(),
            $field instanceof SelectField => $this->select($field, $label, $column, $context),
            $field instanceof MediaField => $this->media($field, $label, $column),
            $field instanceof LinkField => $this->link($field, $label, $column, $context),
            $field instanceof EntityField => $this->entity($field, $label, $column),
            $field instanceof GroupField => Fieldset::make($label, $this->fieldsOf($field->getFields(), $column, context: $context)),
            $field instanceof RepeaterField => $this->repeater($field, $label, $column, $context),
            $field instanceof ColorField => Color::make($label, $column),
            $field instanceof CoordinatesField => $this->coordinates($label, $column),
            default => throw UnsupportedFieldTypeException::forField($field),
        };
    }

    /**
     * A choice the owner leaves open is a select; a choice it settles is no
     * choice at all, so the field disappears and the only value it offers
     * travels as a hidden input. The editor fills that input, and the save
     * settles the value again on the server, see {@see
     * \Ewk\MoonShineCms\Support\FixedFieldValues}.
     */
    private function select(SelectField $field, string $label, string $column, FieldContext $context): MoonShineField
    {
        $options = $field->optionsIn($context);

        return count($options) === 1
            ? Hidden::make('', $column)
            : Select::make($label, $column)->options($options);
    }

    private function richText(string $label, string $column): MoonShineField
    {
        $class = $this->richTextField;

        return new $class($label, $column);
    }

    private function limited(MoonShineField $component, ?int $maxLength): MoonShineField
    {
        return $maxLength === null
            ? $component
            : $component->customAttributes(['maxlength' => (string) $maxLength]);
    }

    private function number(NumberField $field, string $label, string $column): Number
    {
        $number = Number::make($label, $column);

        if ($field->getMin() !== null) {
            $number = $number->min($field->getMin());
        }

        if ($field->getMax() !== null) {
            $number = $number->max($field->getMax());
        }

        // An integer field steps by one; a decimal one must not be rounded by
        // the browser, and `any` is the only portable way to say so. The
        // attribute already carries the default of the field, so it is
        // replaced and not merged.
        return $field->isInteger()
            ? $number->step(1)
            : $number->setAttribute('step', 'any');
    }

    /**
     * A reference to a file of the library plus the description this one place
     * gives it. A picture is chosen in the library dialog or uploaded straight
     * from the desktop, and either way it is a record of the library before the
     * card is saved.
     *
     * The kinds are the ones the schema declares, and the default of the
     * picker — a picture or an icon — where it declares none: a video or a
     * price list is a deliberate choice of the block, never a default.
     */
    private function media(MediaField $field, string $label, string $column): MediaPicker
    {
        $picker = MediaPicker::make($label, $column);

        return $field->getKinds() === [] ? $picker : $picker->kinds($field->getKinds());
    }

    /**
     * The kind of the target plus the inputs that identify it, shown by the
     * chosen kind. Nothing is stored resolved.
     *
     * The visibility is the field's own Alpine state and not MoonShine's
     * `showWhen`: a row of a repeater renames every input, and a condition
     * written against the name of the top level never matched there.
     */
    private function link(LinkField $field, string $label, string $column, FieldContext $context): LinkPicker
    {
        // The sections of «this page» are the blocks of the owner, so the
        // picker is told who that owner is.
        return LinkPicker::make($label, $column)
            ->allowedKinds($field->getAllowedKinds())
            ->forOwner($context->owner);
    }

    private function entity(EntityField $field, string $label, string $column): EntityPicker
    {
        $picker = EntityPicker::make($label, $column);

        // Where the panel answers over the wire the field is searched instead
        // of preloaded: the document then carries the captions of the chosen
        // records and not the first hundred rows of a table.
        $url = LinkEndpoints::url('entities');

        $picker = $url === null
            ? $picker->targets($field->getTargets(), $this->entityOptionsLimit)->searchable()
            : $picker->searchAt($url.'?'.http_build_query(['type' => $field->getTargets()]), $field->getTargets());


        return $field->isMultiple() ? $picker->multiple() : $picker;
    }

    private function repeater(RepeaterField $field, string $label, string $column, FieldContext $context): Json
    {
        $fields = $this->fieldsOf($field->getFields(), '', flat: true, context: $context);

        $json = Json::make($label, $column)
            ->fields($fields)
            ->creatable(limit: $field->getMax())
            ->removable();

        // A row of a repeater is folded away by a head the script builds, see
        // cms-editor.js: the marker says where to look, and the captions
        // travel with it because an inlined script carries no translations.
        $json->addAssets(EditorAssets::inline());
        $json->customWrapperAttributes([
            'data-cms-repeater' => '',
            'data-cms-row-label' => (string) __('moonshine-cms::editor.row'),
            'data-cms-row-toggle' => (string) __('moonshine-cms::editor.row_toggle'),
        ]);

        // A table row is one `<td>` per field: a plain input fits it, a
        // composite field (link, media, group, nested repeater) renders its
        // own label and several inputs of its own and breaks the row's grid
        // instead of sitting inside a cell. `vertical()` draws every row as
        // a labelled card stack instead, which any field — plain or
        // composite — renders correctly.
        //
        // A language copy needs the same card stack for a reason of its own: a
        // table hands every field one column of the row and its own heading,
        // so the languages of one caption would stand side by side under two
        // identical headings, and a heading cannot be hidden per row.
        $localised = $this->hasLocaleCopy($fields);

        if ($this->hasCompositeField($fields) || $localised) {
            $json->vertical();
        }

        if ($localised) {
            $json->modifyTable(
                static fn (TableBuilderContract $table, bool $preview): TableBuilderContract => $preview
                    ? $table
                    : $table->vertical(title: self::verticalTitle(...), value: self::verticalValue(...)),
            );
        }

        return $json;
    }

    /**
     * The caption of one field of a vertical repeater row. MoonShine draws it
     * in a column of its own, outside the wrapper {@see self::markLocale()}
     * marks, so the caption of a hidden language stayed on the screen with an
     * empty place under it and the row read as one field too many. The caption
     * is given the visibility of its input and nothing else: the badge stays
     * on the input, so the language is still named exactly once.
     */
    private static function verticalTitle(FieldContract $field, ComponentContract $default): ComponentContract
    {
        // The drag handle takes the place of the caption, see
        // {@see \MoonShine\UI\Fields\Json::resolveRender()}.
        if ($field->getColumn() === '__handle') {
            return Column::make([$field])->columnSpan(12);
        }

        /** @var Column $column */
        $column = $default;
        $column = $column->columnSpan(12);

        $wrapper = $field->getWrapperAttributes();
        $locale = $wrapper->get('data-cms-locale');

        if (! is_string($locale) || $locale === '') {
            return $column;
        }

        $attributes = [
            // A mark of its own and not `data-cms-locale`: the badge of the
            // editor is drawn from that one, and the caption must not repeat it.
            'data-cms-locale-label' => $locale,
            'x-show' => self::LOCALE_SHOWN.'$el.dataset.cmsLocaleLabel',
        ];

        $style = $wrapper->get('style');

        if (is_string($style) && $style !== '') {
            $attributes['style'] = $style;
        }

        return $column->customAttributes($attributes);
    }

    /**
     * The input of one field of a vertical repeater row, kept exactly as
     * MoonShine draws it: the caption above it is the only thing that changes,
     * and both callbacks travel together.
     */
    private static function verticalValue(FieldContract $field, ComponentContract $default): ComponentContract
    {
        if ($field->getColumn() === '__handle') {
            return Column::make()->columnSpan(12);
        }

        /** @var Column $column */
        $column = $default;

        return $column->columnSpan(12)->customAttributes(['data-validation-wrapper' => true]);
    }

    /**
     * Whether a repeater row carries a language copy of a translatable field,
     * that is a field {@see self::markLocale()} has marked.
     *
     * @param  list<ComponentContract>  $fields
     */
    private function hasLocaleCopy(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field instanceof MoonShineField
                && $field->getWrapperAttributes()->get('data-cms-locale') !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ComponentContract>  $fields
     */
    private function hasCompositeField(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field instanceof Fieldset
                || $field instanceof Json
                || $field instanceof CompositeValueField) {
                return true;
            }
        }

        return false;
    }

    private function coordinates(string $label, string $column): Fieldset
    {
        return Fieldset::make($label, [
            Number::make((string) __('moonshine-cms::editor.latitude'), $column.'.lat')
                ->min(-90)
                ->max(90)
                ->setAttribute('step', 'any'),
            Number::make((string) __('moonshine-cms::editor.longitude'), $column.'.lng')
                ->min(-180)
                ->max(180)
                ->setAttribute('step', 'any'),
        ]);
    }

    /**
     * Everything the declaration says about a field that is not its type: the
     * label is already set, the rest travels here.
     */
    private function carry(Field $field, MoonShineField $component, string $prefix): MoonShineField
    {
        // A settled value is drawn as a hidden input: it has no label, no
        // hint and no condition to show under, and `required` on a hidden
        // input is what makes a browser refuse to submit the form at all.
        if ($component instanceof Hidden) {
            return $component;
        }

        if ($field->isRequired()) {
            $component = $component->required();
        }

        if ($field->isNullable()) {
            $component = $component->nullable();

            // The empty entry of a select reads as a word: a dash is a mark of
            // a table and not a choice an editor makes.
            if ($component instanceof Select) {
                $component = $component->customAttributes([
                    'placeholder' => (string) __('moonshine-cms::fields.not_chosen'),
                ]);
            }
        }

        $help = $field->getHelp();

        if ($help !== null) {
            $component = $component->hint($help);
        }

        $default = $field->getDefault();

        if ($default !== null && $component instanceof HasDefaultValueContract) {
            $component->default($default);
        }

        $visibleWhen = $field->getVisibleWhen();

        if ($visibleWhen !== null) {
            // The controlling field is a sibling of the same block, and it is
            // never a translatable one: a condition on a language would differ
            // per tab.
            $component = $component->showWhen(
                $prefix === '' ? $visibleWhen[0] : $prefix.'.'.$visibleWhen[0],
                '=',
                $visibleWhen[1],
            );
        }

        return $component;
    }
}
