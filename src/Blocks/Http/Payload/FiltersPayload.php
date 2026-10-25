<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Payload;

use stdClass;

/**
 * The `filters` object inside the `data` of a list block.
 *
 * A declared parameter and the available values of a filter are two different
 * things: the schema declares the type and the limits, the client passes the
 * chosen value in the query string, and the payload hands back the options the
 * block itself computed — the months of a schedule come from the real dates of
 * its events.
 *
 * There are no parameter names in the payload: the frontend follows ready
 * addresses. In the `redirect` mode the address of the target is added, and the
 * list of the response is empty.
 */
final class FiltersPayload
{
    public const MODE_INLINE = 'inline';

    public const MODE_REDIRECT = 'redirect';

    /**
     * @param  string  $mode  `inline` filters its own list, `redirect` leads away with the filters carried over
     * @param  array<string, array{value?: mixed, options?: mixed}>  $fields  parameter name => chosen value and its options
     * @param  string|null  $redirectHref  the target of the `redirect` mode
     * @return array{mode: string, fields: non-empty-array<string, array{value: mixed, options: list<array{value: mixed, label: string}>}>|stdClass, redirect?: array{href: string}}
     */
    public static function make(string $mode, array $fields, ?string $redirectHref = null): array
    {
        $built = [];

        foreach ($fields as $name => $field) {
            $options = $field['options'] ?? [];

            $built[$name] = [
                'value' => $field['value'] ?? null,
                'options' => is_array($options) ? self::options($options) : [],
            ];
        }

        $payload = ['mode' => $mode, 'fields' => $built === [] ? new stdClass() : $built];

        if ($mode === self::MODE_REDIRECT && $redirectHref !== null) {
            $payload['redirect'] = ['href' => $redirectHref];
        }

        return $payload;
    }

    /**
     * An option is a pair of `value` and `label`, and nothing else in the first
     * version. Both spellings are accepted, a list of ready pairs and a map of
     * value to label; the map cannot express the `null` value of an "all"
     * option, so a filter with a default writes the pairs out.
     *
     * @param  array<array-key, mixed>  $options
     * @return list<array{value: mixed, label: string}>
     */
    private static function options(array $options): array
    {
        $built = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $label = $option['label'] ?? '';

                $built[] = [
                    'value' => $option['value'] ?? null,
                    'label' => is_scalar($label) ? (string) $label : '',
                ];

                continue;
            }

            $built[] = [
                'value' => $key,
                'label' => is_scalar($option) ? (string) $option : '',
            ];
        }

        return $built;
    }
}
