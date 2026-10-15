<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests;

use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\CmsBlocksServiceProvider;
use Ewk\Cms\Media\CmsMediaServiceProvider;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\CmsPagesServiceProvider;
use Ewk\Cms\Pages\Tests\Fixtures\Blocks\ListingBlock;
use Ewk\Cms\Pages\Tests\Fixtures\StaticLanguageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // Pages own blocks, and assembling a template creates them: the
        // blocks and the media library they stand on boot here too, in the
        // order of an application, where the providers of this package are
        // discovered before those of `ewk/moonshine-media`.
        return [
            CmsBlocksServiceProvider::class,
            CmsPagesServiceProvider::class,
            CmsMediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // The production database enforces foreign keys, so the tests do
            // too: a page points at its parent and a block at its owner.
            'foreign_key_constraints' => true,
        ]);

        // The language catalog is a contract of the media library, whose
        // provider binds a configuration driven default in an application. Binding a fixture here keeps these tests about
        // addresses instead of about somebody else's configuration.
        $app->bind(LanguageCatalog::class, fn (): LanguageCatalog => new StaticLanguageCatalog());

        // The starter set of the test template names `hero` and `checkout`;
        // only `text` is registered, so assembling also proves that an
        // unregistered code is skipped and not fatal.
        $app['config']->set('cms-blocks.blocks', [TextBlock::class, ListingBlock::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
