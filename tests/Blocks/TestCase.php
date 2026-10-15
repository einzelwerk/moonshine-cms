<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests;

use Ewk\Cms\Blocks\CmsBlocksServiceProvider;
use Ewk\Cms\Media\CmsMediaServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // The media library is a dependency: the core registers what a block
        // uses, and its migrations carry `cms_media` and `cms_media_usages`.
        // In the order of an application, where the providers of this
        // package are discovered before those of `ewk/moonshine-media`.
        return [CmsBlocksServiceProvider::class, CmsMediaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // The production database enforces foreign keys, so the tests do
            // too: the reference from a block to a shared block is a real
            // constraint.
            'foreign_key_constraints' => true,
        ]);

        // The content languages the suite writes and reads: Russian, with
        // English beside it.
        $app['config']->set('cms-media.locales', ['ru', 'en']);
        $app['config']->set('cms-media.default_locale', 'ru');
    }

    /**
     * A table for the owner fixtures: blocks always belong to an owner, and
     * publication snapshots one.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (Schema::hasTable('test_block_owners')) {
            return;
        }

        Schema::create('test_block_owners', function ($table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
