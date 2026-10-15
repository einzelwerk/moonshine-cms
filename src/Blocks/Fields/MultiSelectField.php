<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Closure;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A list of values out of a declared set.
 */
final class MultiSelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    /**
     * @param  array<string, string>  $options  value => label
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        $values = [];

        foreach ($raw as $value) {
            $value = self::stringOrNull($value);

            if ($value !== null) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    public function rules(): array
    {
        $allowed = array_keys($this->options);

        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
                if (! is_array($value) || $allowed === []) {
                    return;
                }

                foreach ($value as $item) {
                    if (! is_string($item) || ! in_array($item, $allowed, true)) {
                        $fail((string) __('cms-blocks::validation.multi_select.options'));

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
}
