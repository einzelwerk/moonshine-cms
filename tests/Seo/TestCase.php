<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests;

use Ewk\Cms\Blocks\CmsBlocksServiceProvider;
use Ewk\Cms\Media\CmsMediaServiceProvider;
use Ewk\Cms\Pages\CmsPagesServiceProvider;
use Ewk\Cms\Seo\CmsSeoServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Media is booted because the picture of a card is a reference to the
     * library, pages because metadata of a page is the first case of an owner
     * and its polymorphic alias is part of the contract, blocks because pages
     * stand on it. In the order of an application, where the providers of
     * this package are discovered before those of `ewk/moonshine-media`.
     */
    protected function getPackageProviders($app): array
    {
        return [
            CmsBlocksServiceProvider::class,
            CmsPagesServiceProvider::class,
            CmsSeoServiceProvider::class,
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
            // too: a usage of a picture points at a record of the library.
            'foreign_key_constraints' => true,
        ]);

        // The content languages the suite writes and reads: Russian, with
        // English beside it.
        $app['config']->set('cms-media.locales', ['ru', 'en']);
        $app['config']->set('cms-media.default_locale', 'ru');

        $app['config']->set('cms-seo.site_url', 'https://example.com');
        $app['config']->set('cms-seo.locale_prefix', 'always');
        $app['config']->set('cms-seo.image_preset', null);
    }

    /**
     * The table of the fixture owner. The package ships no owner of its own:
     * metadata belongs to a page, a tour, an event, a venue, a city or an
     * artist, and all of those are models of the installation.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (! Schema::hasTable('test_seo_owners')) {
            Schema::create('test_seo_owners', function (Blueprint $table): void {
                $table->id();
                $table->json('name')->nullable();
                $table->string('cover_url')->nullable();
                $table->timestamps();
            });
        }
    }
}
