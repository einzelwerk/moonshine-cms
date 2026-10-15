<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * A typed link as it is stored: the kind of the target, the attributes that
 * identify it and whether the editor wants a new tab. The resolved address is
 * never stored.
 */
final readonly class Link
{
    /**
     * @param  array<string, mixed>  $attributes  kind specific target identifiers
     */
    public function __construct(
        public LinkKind $kind,
        public array $attributes = [],
        public bool $newTab = false,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kind = $data['kind'] ?? null;
        $attributes = $data['attributes'] ?? [];

        return new self(
            kind: $kind instanceof LinkKind ? $kind : LinkKind::from(is_string($kind) ? $kind : ''),
            attributes: is_array($attributes) ? self::stringKeyed($attributes) : [],
            newTab: (bool) ($data['new_tab'] ?? false),
        );
    }

    /**
     * @return array{kind: string, attributes: array<string, mixed>, new_tab: bool}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'attributes' => $this->attributes,
            'new_tab' => $this->newTab,
        ];
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $attributes): array
    {
        $keyed = [];

        foreach ($attributes as $name => $value) {
            $keyed[(string) $name] = $value;
        }

        return $keyed;
    }
}
