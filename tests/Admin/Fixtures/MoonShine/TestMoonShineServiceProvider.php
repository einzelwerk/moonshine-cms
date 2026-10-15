<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

/**
 * What the application provider of MoonShine does in a real installation:
 * registers the resources of the panel. The package itself ships no resource
 * for an owner, the application does.
 */
final class TestMoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([EditorOwnerResource::class])
            ->pages([...$core->getConfig()->getPages()]);
    }
}
