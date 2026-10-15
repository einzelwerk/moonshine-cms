<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Params\OptionsProvider;

/**
 * An options provider that counts how often it was asked: it stands in for the
 * database query a real one makes, so a test can see a resolution happen twice.
 */
final class CountingCityOptions implements OptionsProvider
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function options(): array
    {
        self::$calls++;

        return ['berlin' => 'Берлин', 'koeln' => 'Кёльн'];
    }
}
