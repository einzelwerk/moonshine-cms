<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

final class BooleanParam extends Param
{
    public function rules(): array
    {
        return ['boolean'];
    }

    public function normalize(mixed $raw): mixed
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (! is_scalar($raw)) {
            return null;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
    }
}
