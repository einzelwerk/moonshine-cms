<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo;

use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry;
use Ewk\Cms\Seo\JsonLd\JsonLdRegistry;
use Ewk\Cms\Seo\Media\MetadataUsageLabelResolver;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Resolve\SeoResolveContributor;
use Ewk\Cms\Seo\Sitemap\SitemapRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CmsSeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cms-seo.php', 'cms-seo');

        // Which structured data a page carries is knowledge of the application;
        // the registry starts empty and the core ships no builder.
        $this->app->singleton(JsonLdRegistry::class);

        // The sources of the sitemap are derived from the registry of page
        // types; an installation narrows a type or adds a source of its own
        // through an extender, exactly as it adds a builder of structured data.
        $this->app->singleton(SitemapRegistry::class);
    }

    public function boot(): void
    {
        // The polymorphic columns of the CMS carry aliases and not class
        // names, so that a model may be renamed without a data migration.
        Relation::morphMap([CmsMetadata::MORPH_ALIAS => CmsMetadata::class]);

        // How the SEO object and the structured data reach `GET /v1/resolve`:
        // the pages know nothing about the SEO part, so the answer is extended
        // by a contributor.
        //
        // An extender and not a `make()` in `boot()`: the registry is a
        // singleton, and resolving it here would either run before the
        // neighbours had registered theirs or register ours twice on a
        // container that boots its providers more than once.
        $this->app->extend(
            ResolveContributorRegistry::class,
            static function (ResolveContributorRegistry $registry, Application $app): ResolveContributorRegistry {
                $registry->register($app->make(SeoResolveContributor::class));

                return $registry;
            },
        );

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/seo');
        $this->loadTranslationsFrom(__DIR__.'/../../lang/cms-seo', 'cms-seo');
        $this->registerRoutes();

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations/seo' => database_path('migrations'),
        ], 'cms-seo-migrations');

        $this->publishes([
            __DIR__.'/../../config/cms-seo.php' => config_path('cms-seo.php'),
        ], 'cms-seo-config');

        $this->publishes([
            __DIR__.'/../../lang/cms-seo' => $this->app->langPath('vendor/cms-seo'),
        ], 'cms-seo-lang');

        $this->registerUsageLabels();
    }

    /**
     * The sitemap feed is an endpoint of the package, so the package registers
     * it, the way the pages register their resolver. The prefix and the
     * middleware come from the configuration.
     */
    private function registerRoutes(): void
    {
        $routes = $this->app->make('config')->get('cms-seo.sitemap.routes', []);

        if (! is_array($routes)) {
            return;
        }

        $prefix = $routes['prefix'] ?? 'api/v1';
        $middleware = $routes['middleware'] ?? ['api'];

        Route::group([
            'prefix' => is_string($prefix) ? $prefix : 'api/v1',
            'middleware' => is_array($middleware) || is_string($middleware) ? $middleware : ['api'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/seo.php');
        });
    }

    /**
     * What this package is called in the list of «where this file is used».
     *
     * An extender and not a `make()`, for the same reason the contributor
     * above is one: the registry is a singleton, and resolving it here would
     * either run before the neighbours registered theirs or register ours
     * twice on a container that boots its providers more than once.
     */
    private function registerUsageLabels(): void
    {
        $this->app->extend(
            UsageLabelRegistry::class,
            static function (UsageLabelRegistry $registry): UsageLabelRegistry {
                $registry->register(new MetadataUsageLabelResolver($registry));

                return $registry;
            },
        );
    }
}
