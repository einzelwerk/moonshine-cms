<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Tests;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\Resolvers\UrlLinkResolver;
use Ewk\Cms\Blocks\Media\ContentMediaUsages;
use Ewk\Cms\Blocks\Media\MediaReferenceCollector;
use Ewk\Cms\Layouts\CmsLayoutsServiceProvider;
use Ewk\Cms\Layouts\Tests\Fixtures\StaticLanguageCatalog;
use Ewk\Cms\Media\CmsMediaServiceProvider;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // The media library brings `cms_media` and `cms_media_usages`: a
        // header and a footer register what they use. In the order of an
        // application, where the providers of this package are discovered
        // before those of `ewk/moonshine-media`.
        return [CmsLayoutsServiceProvider::class, CmsMediaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // The production database enforces foreign keys, so the tests do
            // too: a layout points at a header and a footer, a menu item at
            // its menu.
            'foreign_key_constraints' => true,
        ]);

        // The catalog is a contract of the media library and the registry one
        // of the blocks, both bound by their providers in an application. Binding them here keeps these tests about the
        // chrome and, for the resolvers, makes the interesting case explicit:
        // only external addresses have a resolver, so a link to an entity is
        // a link whose target this installation cannot resolve.
        $app->bind(LanguageCatalog::class, fn (): LanguageCatalog => new StaticLanguageCatalog());

        $app->singleton(LinkResolverRegistry::class, static function (): LinkResolverRegistry {
            $resolvers = new LinkResolverRegistry();
            $resolvers->register(new UrlLinkResolver());

            return $resolvers;
        });

        // Bound by the provider of the blocks package in an application; this
        // suite does not boot that provider, because it also rebinds the link
        // resolvers the test above pins on purpose.
        $app->singleton(MediaReferenceCollector::class);
        $app->singleton(ContentMediaUsages::class);
    }
}
