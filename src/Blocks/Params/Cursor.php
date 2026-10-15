<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The opaque position of a cursor page. Inside it are the sort key of the last
 * element and its identifier, `{k, i}`; outside it is a base64url string that
 * promises nothing between releases.
 *
 * Rubbish decodes to `null` rather than to an error: a stale cursor is the
 * fault of an address that travelled, not of the client holding it, and the
 * block opens its list from the beginning.
 */
final class Cursor
{
    /**
     * @param  array<string, mixed>  $position  `{k: sort key, i: id}`
     */
    public static function encode(array $position): string
    {
        $json = json_encode($position, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $cursor): ?array
    {
        if ($cursor === '') {
            return null;
        }

        $padded = strtr($cursor, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $json = base64_decode($padded, true);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
