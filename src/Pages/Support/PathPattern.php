<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;

/**
 * The path template declared by a page type. The very same declaration serves
 * both directions: it parses an incoming address into its placeholders and
 * builds the canonical address of a subject back. There is no second list to
 * keep in sync.
 */
final readonly class PathPattern
{
    private const string PLACEHOLDER = '/^\{([a-z][a-z0-9_]*)\}$/';

    /**
     * @param  list<string>  $segments  raw segments, placeholders included
     * @param  list<string>  $placeholders
     */
    private function __construct(
        public string $pattern,
        private array $segments,
        private array $placeholders,
    ) {
    }

    /**
     * @throws InvalidPageTypeException when a segment looks like a malformed placeholder
     */
    public static function fromString(string $pattern): self
    {
        $segments = PathNormalizer::segments($pattern);
        $placeholders = [];

        foreach ($segments as $segment) {
            if (preg_match(self::PLACEHOLDER, $segment, $matches) === 1) {
                $placeholders[] = $matches[1];

                continue;
            }

            if (str_contains($segment, '{') || str_contains($segment, '}')) {
                throw InvalidPageTypeException::malformedPlaceholder($pattern, $segment);
            }
        }

        return new self(PathNormalizer::normalize($pattern), $segments, $placeholders);
    }

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    public function isStatic(): bool
    {
        return $this->placeholders === [];
    }

    /**
     * The placeholder values of an address, or null when the address is not
     * described by this template at all.
     *
     * @param  list<string>  $segments  normalized segments of the requested address
     * @return array<string, string>|null
     */
    public function match(array $segments): ?array
    {
        if (count($segments) !== count($this->segments)) {
            return null;
        }

        $values = [];

        foreach ($this->segments as $index => $expected) {
            $actual = $segments[$index];

            if (preg_match(self::PLACEHOLDER, $expected, $matches) === 1) {
                $values[$matches[1]] = $actual;

                continue;
            }

            if ($expected !== $actual) {
                return null;
            }
        }

        return $values;
    }

    /**
     * The canonical address for a set of placeholder values, or null when a
     * value cannot be one segment of an address. A placeholder without a value
     * keeps its braces, so a half built address is visible instead of quietly
     * collapsing into a shorter, wrong one.
     *
     * @param  array<string, string>  $values
     */
    public function build(array $values): ?string
    {
        $segments = [];

        foreach ($this->segments as $segment) {
            if (preg_match(self::PLACEHOLDER, $segment, $matches) === 1) {
                $value = $values[$matches[1]] ?? null;

                if ($value === null) {
                    $segments[] = $segment;

                    continue;
                }

                if (! self::isSegment($value)) {
                    return null;
                }

                $segments[] = $value;

                continue;
            }

            $segments[] = $segment;
        }

        return PathNormalizer::normalize(implode('/', $segments));
    }

    /**
     * One value fills one segment, and a value carrying `/`, `?` or `#` fills
     * more than that: `x?next=//evil.com` is not a slug of this site but a
     * different address with a different host at the end of it.
     *
     * Escaping it instead of refusing it would not help: the one normalization
     * of the CMS expands percent encoding, see
     * {@see PathNormalizer::segments()}, so `%2F` would come back a slash a
     * step later. Such a value has no address at all, which is the answer a
     * record that is gone already gets.
     */
    private static function isSegment(string $value): bool
    {
        foreach ([$value, rawurldecode($value)] as $candidate) {
            if (trim($candidate) === '' || preg_match('#[/?\#]#', $candidate) === 1) {
                return false;
            }
        }

        return true;
    }
}
