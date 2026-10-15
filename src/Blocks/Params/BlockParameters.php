<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The raw parameter sets of one request, before any schema has seen them.
 *
 * Two entrances share one grammar. A page request namespaces every set by the
 * anchor of its block (`b[raspisanie][page]=2`), so two lists on one page never
 * divide `page` or `shown` between them. A request for the data of a single
 * block carries its parameters in a flat query string, because there is only
 * one block in it.
 *
 * Which of the two was parsed is remembered: the key of a validation error
 * follows the shape of the address the client actually sent.
 */
final readonly class BlockParameters
{
    /**
     * @param  array<string, array<string, mixed>>  $sets  anchor => raw parameters
     */
    private function __construct(
        private array $sets,
        private bool $single,
    ) {
    }

    /**
     * The query string of a page: every set sits under `b[anchor]`. Anything
     * that is not a set of parameters is dropped here rather than later.
     *
     * @param  array<array-key, mixed>  $query
     */
    public static function fromPageQuery(array $query): self
    {
        $namespaced = $query['b'] ?? null;
        $sets = [];

        if (is_array($namespaced)) {
            foreach ($namespaced as $anchor => $set) {
                if (is_array($set)) {
                    $sets[(string) $anchor] = self::flat($set);
                }
            }
        }

        return new self($sets, false);
    }

    /**
     * The query string of the endpoint of one block: the whole of it belongs to
     * that block, and what the schema does not declare is dropped by the
     * resolution.
     *
     * @param  array<array-key, mixed>  $query
     */
    public static function forSingle(string $anchor, array $query): self
    {
        return new self([$anchor => self::flat($query)], true);
    }

    /**
     * A request that carries no parameters at all: every list opens in its
     * first state.
     */
    public static function none(): self
    {
        return new self([], false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rawFor(string $anchor): array
    {
        return $this->sets[$anchor] ?? [];
    }

    /**
     * @return list<string>
     */
    public function anchors(): array
    {
        return array_keys($this->sets);
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    /**
     * The key of a validation error as the client wrote it: namespaced by the
     * anchor for a page request, bare for the endpoint of one block.
     */
    public function errorKey(string $anchor, string $name): string
    {
        return $this->single ? $name : 'b.'.$anchor.'.'.$name;
    }

    /**
     * An empty value means "not provided": a filter with a non-empty default
     * declares its own "all" value instead of sending an empty one.
     *
     * @param  array<array-key, mixed>  $set
     * @return array<string, mixed>
     */
    private static function flat(array $set): array
    {
        $flat = [];

        foreach ($set as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $flat[(string) $name] = $value;
        }

        return $flat;
    }
}
