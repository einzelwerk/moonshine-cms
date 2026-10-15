<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Fields;

use Ewk\MoonShineCms\Contracts\PickerDetailContract;
use Ewk\MoonShineCms\Links\Caption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use MoonShine\UI\Fields\Select;
use Throwable;

/**
 * Searchable select over the Eloquent classes an {@see \Ewk\Cms\Blocks\Fields\EntityField}
 * declares as targets. One option list covers every target, because the stored
 * value is a pair of a type and a key: the option value carries both, so a
 * field that accepts a venue and a concert needs no second input.
 *
 * The list is bounded on purpose: a block field points at a handful of
 * entities, and an admin form must not read a whole table. Where the panel can
 * ask the server — the block editor, the link picker — the field is searched
 * instead of preloaded, and the options rendered into the document are the
 * chosen ones alone, so the form opens with the captions the editor stored and
 * not with the first hundred rows of a table.
 */
final class EntityPicker extends Select
{
    /**
     * Separator between the entity type and its key inside one option value.
     * A morph alias never contains it, a numeric or uuid key never does.
     */
    public const SEPARATOR = '::';

    /**
     * The columns a record is recognised by, in the order they are tried. The
     * same list answers «what is this record called» and «what is searched
     * when the editor types».
     *
     * @var list<string>
     */
    public const LABEL_COLUMNS = ['name', 'title', 'label', 'code', 'slug'];

    /** @var list<string> */
    protected array $targets = [];

    protected int $optionsLimit = 100;

    protected bool $searched = false;

    /**
     * The options are a closure and not an array: a field is built before the
     * form hands it its value, and what has to be offered depends on that
     * value — the chosen records where the field is searched, the first page
     * of the table where it is not.
     *
     * @param  list<string>  $targets  model classes or morph aliases, as the schema declares them
     */
    public function targets(array $targets, int $limit = 100): static
    {
        $this->targets = $targets;
        $this->optionsLimit = $limit;

        // The closure takes the field it is asked on: the form fills a copy
        // of every field, and a closure bound to the original would read the
        // value of a field nobody filled.
        return $this->options(static fn (self $field): array => $field->offeredOptions());
    }

    /**
     * The same field asked over the wire: the document carries the chosen
     * options and their captions, everything else arrives as the editor types.
     *
     * @param  list<string>  $targets
     */
    public function searchAt(string $url, array $targets): static
    {
        $this->searched = true;

        return $this->targets($targets)->async($url);
    }

    /**
     * The stored reference is a type and a key; the option value carries both.
     * The conversion happens here and not in the form that built the field, so
     * a saved value opens selected wherever this field is drawn.
     */
    protected function resolveValue(): mixed
    {
        $value = parent::resolveValue();
        $packed = self::packedKeys($value);

        if ($packed === []) {
            return $this->isMultiple() ? [] : null;
        }

        return $this->isMultiple() ? $packed : $packed[0];
    }

    /**
     * What this field offers right now: the chosen records where it is
     * searched over the wire, the first page of the table where it is not.
     *
     * @return array<string, string>
     */
    public function offeredOptions(): array
    {
        return $this->searched
            ? self::labelledOptions($this->targets, self::packedKeys($this->toValue()))
            : self::optionsFor($this->targets, $this->optionsLimit);
    }

    /**
     * A value of this field in any of the shapes it travels in: the stored
     * reference, a list of them, or the packed option the form sends back.
     *
     * @return list<string>
     */
    private static function packedKeys(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $type = $value['type'] ?? null;
        $id = $value['id'] ?? null;

        if (is_string($type) && (is_string($id) || is_int($id))) {
            return [self::pack($type, $id)];
        }

        $packed = [];

        foreach ($value as $item) {
            array_push($packed, ...self::packedKeys($item));
        }

        return $packed;
    }

    /**
     * The stored pair as one option value.
     */
    public static function pack(string $type, int|string $key): string
    {
        return $type.self::SEPARATOR.$key;
    }

    /**
     * @return array{type: string, id: int|string}|null
     */
    public static function unpack(mixed $value): ?array
    {
        if (! is_string($value) || ! str_contains($value, self::SEPARATOR)) {
            return null;
        }

        [$type, $key] = explode(self::SEPARATOR, $value, 2);

        if ($type === '' || $key === '') {
            return null;
        }

        return ['type' => $type, 'id' => ctype_digit($key) ? (int) $key : $key];
    }

    /**
     * The records of one target whose caption contains what the editor typed.
     * An empty query answers the first page of the table, which is what a
     * select offers the moment it is opened.
     *
     * @return array<string, string>  packed value => caption
     */
    public static function search(string $target, string $query, int $limit = 50): array
    {
        $model = self::modelOf($target);

        if ($model === null) {
            return [];
        }

        $builder = $model::query();
        $term = trim($query);

        if ($term !== '') {
            $columns = self::searchableColumns($model);

            if ($columns === []) {
                return [];
            }

            $builder->where(static function (Builder $nested) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $nested->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        $options = [];

        foreach ($builder->limit($limit)->get() as $row) {
            $key = $row->getKey();

            if (! is_int($key) && ! is_string($key)) {
                continue;
            }

            $options[self::pack($target, $key)] = self::labelOf($row, $key);
        }

        return $options;
    }

    /**
     * What one stored reference is called, so a saved link opens with the name
     * of its target and not with its key.
     */
    public static function captionOf(string $target, int|string $id): ?string
    {
        $model = self::modelOf($target);

        if ($model === null) {
            return null;
        }

        try {
            $row = $model::query()->whereKey($id)->first();
        } catch (Throwable) {
            return null;
        }

        return $row instanceof Model ? self::labelOf($row, $id) : null;
    }

    /**
     * A target is either a model class or a morph alias of one.
     *
     * @return class-string<Model>|null
     */
    public static function modelOf(string $target): ?string
    {
        $class = class_exists($target) ? $target : Relation::getMorphedModel($target);

        return \is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @param  list<string>  $targets
     * @return array<string, string>
     */
    private static function optionsFor(array $targets, int $limit): array
    {
        $options = [];

        foreach ($targets as $target) {
            $model = self::modelOf($target);

            if ($model === null) {
                continue;
            }

            // The stored type is the string the schema declares, because the
            // validation rule of the core compares against exactly that.
            $type = $target;

            foreach ($model::query()->limit($limit)->get() as $row) {
                $key = $row->getKey();

                if (! is_int($key) && ! is_string($key)) {
                    continue;
                }

                $options[self::pack($type, $key)] = self::labelOf($row, $key);
            }
        }

        return $options;
    }

    /**
     * The chosen values with their captions and nothing else.
     *
     * @param  list<string>  $targets
     * @param  list<string>  $chosen  packed values
     * @return array<string, string>
     */
    private static function labelledOptions(array $targets, array $chosen): array
    {
        $options = [];

        foreach ($chosen as $value) {
            $reference = self::unpack($value);

            if ($reference === null || ! in_array($reference['type'], $targets, true)) {
                continue;
            }

            $caption = self::captionOf($reference['type'], $reference['id']);

            if ($caption !== null) {
                $options[$value] = $caption;
            }
        }

        return $options;
    }

    /**
     * The caption columns this table actually has: a `like` over a column that
     * is not there is an error of the database and not an empty answer.
     *
     * @param  class-string<Model>  $model
     * @return list<string>
     */
    private static function searchableColumns(string $model): array
    {
        try {
            $instance = new $model();
            /** @var SchemaBuilder $schema */
            $schema = $instance->getConnection()->getSchemaBuilder();
            $table = $instance->getTable();

            return array_values(array_filter(
                self::LABEL_COLUMNS,
                static fn (string $column): bool => $schema->hasColumn($table, $column),
            ));
        } catch (Throwable) {
            return [];
        }
    }

    private static function labelOf(Model $row, int|string $key): string
    {
        $caption = self::nameOf($row) ?? class_basename($row).' #'.$key;
        $detail = $row instanceof PickerDetailContract ? $row->pickerDetail() : null;

        return $detail === null || trim($detail) === '' ? $caption : $caption.' ('.$detail.')';
    }

    private static function nameOf(Model $row): ?string
    {
        foreach (self::LABEL_COLUMNS as $column) {
            $caption = Caption::of($row->getAttribute($column));

            if ($caption !== null) {
                return $caption;
            }
        }

        return null;
    }
}
