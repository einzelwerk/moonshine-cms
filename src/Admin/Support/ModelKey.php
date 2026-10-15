<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The key of a record that is stored. `Model::getKey()` answers `mixed`
 * because a model may have no key yet; every caller in the panel works with a
 * row that exists, and an address or a foreign key built from a record without
 * a key would point at nothing.
 */
final class ModelKey
{
    public static function of(Model $model): int|string
    {
        $key = $model->getKey();

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw new LogicException('A '.$model::class.' without a key has no address in the panel.');
    }
}
