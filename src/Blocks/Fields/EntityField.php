<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Closure;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A reference to one or several database records: `{"type": "event", "id": 12}`.
 * The schema declares which entity types may be picked and how many.
 *
 * Expanding the reference into a full entity resource belongs to the
 * application that owns those entities; the payload carries the reference.
 */
final class EntityField extends Field
{
    /** @var list<string> */
    private array $targets = [];

    private bool $multiple = false;

    /**
     * @param  array<array-key, string>  $entityTypes
     */
    public function targets(array $entityTypes): static
    {
        $this->targets = array_values($entityTypes);

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        if (! $this->multiple) {
            return self::reference($raw);
        }

        $references = [];

        foreach ($raw as $item) {
            $reference = is_array($item) ? self::reference($item) : null;

            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    public function rules(): array
    {
        $targets = $this->targets;
        $multiple = $this->multiple;

        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail) use ($targets, $multiple): void {
                if (! is_array($value)) {
                    return;
                }

                $references = $multiple ? $value : [$value];

                foreach ($references as $reference) {
                    if (! is_array($reference) || self::reference($reference) === null) {
                        $fail((string) __('cms-blocks::validation.entity.reference'));

                        return;
                    }

                    $type = $reference['type'] ?? null;

                    if ($targets !== [] && (! is_string($type) || ! in_array($type, $targets, true))) {
                        $fail((string) __('cms-blocks::validation.entity.type_not_allowed', [
                            'type' => is_string($type) ? $type : '?',
                        ]));

                        return;
                    }
                }
            },
        ];
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        return $storedValue === null ? $this->default : $this->normalize($storedValue);
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return array{type: string, id: int|string}|null
     */
    private static function reference(array $raw): ?array
    {
        $type = self::stringOrNull($raw['type'] ?? null);
        $id = $raw['id'] ?? null;

        if ($type === null || (! is_int($id) && ! is_string($id)) || $id === '') {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }
}
