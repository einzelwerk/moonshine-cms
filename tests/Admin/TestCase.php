<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests;

use Ewk\Cms\Blocks\Blocks\ColumnsBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\CmsBlocksServiceProvider;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\CmsLayoutsServiceProvider;
use Ewk\Cms\Media\CmsMediaServiceProvider;
use Ewk\Cms\Media\MoonShine\MoonShineMediaServiceProvider;
use Ewk\Cms\Pages\CmsPagesServiceProvider;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Seo\CmsSeoServiceProvider;
use Ewk\MoonShineCms\MoonShineCmsServiceProvider;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\EveryFieldBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\FeedBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\PanelBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\SlidesBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\SourcedBlock;
use Ewk\MoonShineCms\Tests\Fixtures\Blocks\TemplateOnlyBlock;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\EditorOwnerResource;
use Ewk\MoonShineCms\Tests\Fixtures\MoonShine\TestMoonShineServiceProvider;
use Ewk\MoonShineCms\Tests\Fixtures\TestChromeSchema;
use Ewk\MoonShineCms\Tests\Fixtures\TestPageTypes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * The admin runs inside a MoonShine panel, so the base case boots one: its
 * provider, every provider of this package and of the media library, an
 * application provider that registers the test resource, and an
 * authenticated administrator. The HTTP tests then drive the real MoonShine
 * routes.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // In the order of an application: Composer lists the discovered
        // packages by name, so the providers of this package come before
        // those of `ewk/moonshine-media`, and both before MoonShine itself.
        // The application provider comes last, as `bootstrap/providers.php`.
        return [
            CmsBlocksServiceProvider::class,
            CmsPagesServiceProvider::class,
            CmsLayoutsServiceProvider::class,
            CmsSeoServiceProvider::class,
            MoonShineCmsServiceProvider::class,
            CmsMediaServiceProvider::class,
            MoonShineMediaServiceProvider::class,
            MoonShineServiceProvider::class,
            TestMoonShineServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // The reference from a block to its parent is a real constraint,
            // and the editor has to report what it refuses.
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('cms-blocks.blocks', [
            TextBlock::class,
            // The container of the core: a shareable one, which is what the
            // root of a shared block of several blocks has to be.
            ColumnsBlock::class,
            SharedInsertionBlock::class,
            EveryFieldBlock::class,
            FeedBlock::class,
            PanelBlock::class,
            SlidesBlock::class,
            SourcedBlock::class,
            TemplateOnlyBlock::class,
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // The content languages the suite writes and reads: Russian, with
        // English beside it.
        $app['config']->set('cms-media.locales', ['ru', 'en']);
        $app['config']->set('cms-media.default_locale', 'ru');

        // The metadata form shows the address of the public site in its
        // snippet preview.
        $app['config']->set('cms-seo.site_url', 'https://example.com');

        // The notification centre of the panel needs the Laravel
        // notifications table, which has nothing to do with the editor.
        $app['config']->set('moonshine.use_notifications', false);
        $app['config']->set('moonshine.use_database_notifications', false);
    }

    /**
     * Tables of the owner and of an entity a block may point at. The package
     * ships neither: an application brings its own page model.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (! Schema::hasTable('test_editor_owners')) {
            Schema::create('test_editor_owners', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->default('Owner');
                $table->boolean('is_active')->default(true);
                // The codes a page type would demand of this owner.
                $table->json('required_blocks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('test_catalogue')) {
            Schema::create('test_catalogue', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
        }
    }

    /**
     * What the provider of an installation declares: its page types and the
     * fields of its header.
     */
    protected function setUp(): void
    {
        parent::setUp();

        TestPageTypes::registerAll($this->app->make(PageTypeRegistry::class));
        TestChromeSchema::register($this->app->make(ChromeSchemaRegistry::class));
    }

    protected function actingAsAdmin(): static
    {
        $this->actingAs(MoonshineUser::factory()->create(), 'moonshine');

        return $this;
    }

    protected function resource(): EditorOwnerResource
    {
        return $this->app->make(EditorOwnerResource::class);
    }
}
