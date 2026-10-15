<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Params\OptionsProvider;

final class CityOptions implements OptionsProvider
{
    public function options(): array
    {
        return ['berlin' => 'Берлин', 'koeln' => 'Кёльн'];
    }
}
