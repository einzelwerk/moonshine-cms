<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Params\Pagination;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\ValidationRuleBuilder;
use Ewk\Cms\Blocks\Support\RequiredBlockGuard;
use Ewk\MoonShineCms\Exceptions\BlockVersionConflictException;
use Ewk\MoonShineCms\Support\BlockVersion;
use Ewk\MoonShineCms\Support\FixedFieldValues;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saving one card. Each block is its own request: the structure of a page is
 * heterogeneous, the storage is row per block, and a draft row is invisible to
 * a visitor, so writing it immediately is safe.
 *
 * Two guarantees live here. The version of the row is compared before anything
 * is written, and nothing outside the submitted block is touched — the
 * siblings of a card are not part of its request.
 *
 * A refusal lives here too: the last active instance of a block code the owner
 * requires cannot be switched off — deactivation would be a deletion by another
 * route.
 */
final readonly class SaveBlock
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private ValidationRuleBuilder $rules,
        private ContentAssembler $assembler,
        private RequiredBlockGuard $required,
        private Repository $config,
        private OwnerFieldContext $contexts,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $input  the submitted card
     *
     * @throws BlockVersionConflictException when the row changed since the card was loaded
     * @throws ValidationException
     */
    public function handle(CmsBlock $block, array $input): CmsBlock
    {
        $version = $input['version'] ?? null;

        if (! BlockVersion::matches($block, is_string($version) ? $version : null)) {
            throw BlockVersionConflictException::forBlock(
                $block,
                is_string($version) ? $version : null,
                BlockVersion::of($block),
            );
        }

        $schema = $this->registry->schemaFor($block->type);
        $raw = $input['content'] ?? [];
        $fields = $this->assembler->assemble($schema, is_array($raw) ? $raw : []);

        // Who the block belongs to decides what some of its fields accept. The
        // request is not asked about it: a crafted one would then choose its
        // own rules.
        $context = $this->contexts->for($block->blockable);

        // The settings of the engine live in `content` beside the fields, and
        // the assembler walks the fields only, so they are picked up here.
        $engine = $this->engineSettings($schema, is_array($raw) ? $raw : []);

        // A value the owner leaves no choice about is settled here and not
        // taken from the request: the card carries it in a hidden input, and
        // a request without that input must still store the only value the
        // field could hold.
        $content = FixedFieldValues::of($schema, $context) + $fields + $engine;

        $validator = Validator::make(
            [
                // A card that sends an empty name means it: the panel turns
                // an empty string into null on the way in, and `??` would
                // read that as «not sent» and keep the old one.
                'name' => array_key_exists('name', $input) ? $input['name'] : $block->name,
                'anchor' => $input['anchor'] ?? null,
                'variant' => $input['variant'] ?? $block->variant,
                'is_active' => $input['is_active'] ?? $block->is_active,
                'content' => $content,
            ],
            $this->envelopeRules($schema) + $this->engineRules($schema) + $this->rules->build($schema, $context),
            [],
            $this->envelopeCaptions() + $this->rules->attributes($schema),
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                $this->addressed($validator->errors()->messages(), $schema, is_array($raw) ? $raw : []),
            );
        }

        $validated = $validator->validated();

        // The name is no longer typed by hand: the head of a card reads the
        // content. A card that sends an empty one clears it, a request without
        // the key leaves it alone.
        if (array_key_exists('name', $input)) {
            $block->name = is_string($validated['name'] ?? null) ? $validated['name'] : '';
        }
        $block->anchor = is_string($validated['anchor'] ?? null) && $validated['anchor'] !== ''
            ? $validated['anchor']
            : null;
        $block->variant = is_string($validated['variant'] ?? null) && $validated['variant'] !== ''
            ? $validated['variant']
            : null;
        $active = (bool) ($validated['is_active'] ?? $block->is_active);

        if ($block->is_active && ! $active && $this->required->protectsDeactivation($block)) {
            throw ValidationException::withMessages([
                'is_active' => (string) __('moonshine-cms::editor.required_block'),
            ]);
        }

        $block->is_active = $active;
        $block->content = $content;

        $block->save();

        return $block->refresh();
    }

    /**
     * The settings of the pagination engine the editor is allowed to change.
     * They are stored in `content` but are not fields of the schema, so neither
     * the assembler nor the rule builder of the core knows them; a block type
     * must not declare a field under either name.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array<string, mixed>
     */
    private function engineSettings(BlockSchema $schema, array $raw): array
    {
        $pagination = $schema->pagination();
        $settings = [];

        if ($pagination?->editable === true && array_key_exists('per_page', $raw)) {
            $settings['per_page'] = $raw['per_page'];
        }

        if ($pagination?->modeEditable === true && array_key_exists('pagination_mode', $raw)) {
            $mode = $raw['pagination_mode'];
            $settings['pagination_mode'] = $mode === '' ? null : $mode;
        }

        return $settings;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function engineRules(BlockSchema $schema): array
    {
        $pagination = $schema->pagination();

        if ($pagination === null) {
            return [];
        }

        $rules = [];

        if ($pagination->editable) {
            $rules['content.per_page'] = ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.$this->perPageLimit($pagination)];
        }

        if ($pagination->modeEditable) {
            $modes = array_map(
                static fn (PaginationMode $mode): string => $mode->value,
                $pagination->allowedModes === []
                    ? PaginationMode::cases()
                    : $pagination->allowedModes,
            );

            $rules['content.pagination_mode'] = ['sometimes', 'nullable', Rule::in($modes)];
        }

        return $rules;
    }

    /**
     * The ceiling in force: the smaller of the block's own and the absolute
     * one of the configuration.
     */
    private function perPageLimit(Pagination $pagination): int
    {
        $configured = $this->config->get('cms-blocks.pagination.max_per_page', 100);
        $absolute = is_int($configured) && $configured > 0 ? $configured : 100;

        return max(1, min($pagination->maxPerPage ?? $absolute, $absolute));
    }

    /**
     * Rules of the settings that every block has, whatever its type. The
     * variant is limited to the ones the schema declares: an unknown variant
     * would change the slots of a container.
     *
     * @return array<string, list<mixed>>
     */
    private function envelopeRules(BlockSchema $schema): array
    {
        $variants = array_map(static fn (int|string $code): string => (string) $code, array_keys($schema->variants()));

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'anchor' => ['nullable', 'string', 'max:255', 'regex:/^[\w-]+$/'],
            'variant' => $variants === []
                ? ['nullable']
                : ['nullable', Rule::in($variants)],
            'is_active' => ['boolean'],
            'content' => ['array'],
        ];
    }

    /**
     * The fields of the card that belong to no schema: the panel shows them
     * under «more», and a refusal has to name them the same way.
     *
     * @return array<string, string>
     */
    private function envelopeCaptions(): array
    {
        return [
            'name' => (string) __('moonshine-cms::editor.name'),
            'anchor' => (string) __('moonshine-cms::editor.anchor'),
            'variant' => (string) __('moonshine-cms::editor.variant'),
            'is_active' => (string) __('moonshine-cms::editor.is_active'),
        ];
    }

    /**
     * The errors, keyed the way the card names its inputs. The panel numbers
     * the rows of a repeater as it pleases and never renumbers them after a
     * deletion, while the validator sees a list built out of what arrived:
     * the same row carries two numbers, and only the first one finds the
     * input on the form, see MoonShine\UI FormBuilder::inputsErrors().
     *
     * @param  array<string, array<string>>  $errors
     * @param  array<array-key, mixed>  $raw
     * @return array<string, array<string>>
     */
    private function addressed(array $errors, BlockSchema $schema, array $raw): array
    {
        $rows = $this->rowKeys($schema->fields(), $raw, 'content');

        if ($rows === []) {
            return $errors;
        }

        $addressed = [];

        foreach ($errors as $key => $messages) {
            $addressed[$this->readdress($key, $rows)] = $messages;
        }

        return $addressed;
    }

    /**
     * @param  array<string, list<array-key>>  $rows
     */
    private function readdress(string $key, array $rows): string
    {
        // Two paths are walked at once: the one the validator used, which is
        // what the map is keyed by, and the one the form knows.
        $assembled = '';
        $submitted = '';

        foreach (explode('.', $key) as $segment) {
            $keys = $rows[$assembled] ?? null;
            $named = $segment;

            if ($keys !== null && ctype_digit($segment) && array_key_exists((int) $segment, $keys)) {
                $named = (string) $keys[(int) $segment];
            }

            $assembled = $assembled === '' ? $segment : $assembled.'.'.$segment;
            $submitted = $submitted === '' ? $named : $submitted.'.'.$named;
        }

        return $submitted;
    }

    /**
     * Which keys the rows of every repeater of the schema arrived under, by
     * the path of the repeater. The filter is the one the assembler applies,
     * so the n-th key here is the n-th row it built.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $raw
     * @return array<string, list<array-key>>
     */
    private function rowKeys(array $fields, array $raw, string $prefix): array
    {
        $rows = [];

        foreach ($fields as $field) {
            $key = $prefix.'.'.$field->name();
            $value = $raw[$field->name()] ?? null;

            if ($field instanceof GroupField && is_array($value)) {
                $rows += $this->rowKeys($field->getFields(), $value, $key);

                continue;
            }

            if (! $field instanceof RepeaterField || ! is_array($value)) {
                continue;
            }

            $keys = [];

            foreach ($value as $rowKey => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rows += $this->rowKeys($field->getFields(), $row, $key.'.'.count($keys));
                $keys[] = $rowKey;
            }

            $rows[$key] = $keys;
        }

        return $rows;
    }
}
