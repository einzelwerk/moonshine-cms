<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts;

use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Layouts\LayoutCatalog;
use Ewk\Cms\Layouts\Media\ChromeUsageLabelResolver;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\Cms\Layouts\Selection\LayoutResolveContributor;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CmsLayoutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cms-layouts.php', 'cms-layouts');

        // The fields of a header and of a footer are declared by the
        // application: a logo, a copyright line and payment icons are exactly
        // what a reusable package cannot know.
        $this->app->singleton(ChromeSchemaRegistry::class);

        $this->app->singleton(MenuSlotCatalog::class);

        // Which shells exist is knowledge of the application too: a code
        // without a component in the frontend is an unkept promise, so the
        // catalog starts empty and the installation declares its own.
        $this->app->singleton(LayoutCatalog::class);

        // Which dictionary feeds a menu is knowledge of the application; the
        // registry starts empty.
        $this->app->singleton(MenuSourceRegistry::class);
    }

    public function boot(): void
    {
        // The polymorphic columns of the CMS carry aliases and not class
        // names, so that a model may be renamed without a data migration.
        Relation::morphMap([
            CmsHeader::MORPH_ALIAS => CmsHeader::class,
            CmsFooter::MORPH_ALIAS => CmsFooter::class,
            CmsLayout::MORPH_ALIAS => CmsLayout::class,
            CmsMenu::MORPH_ALIAS => CmsMenu::class,
            CmsMenuItem::MORPH_ALIAS => CmsMenuItem::class,
            CmsMenuSlotAssignment::MORPH_ALIAS => CmsMenuSlotAssignment::class,
        ]);

        $this->registerLayoutContributor();
        $this->registerUsageLabels();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/layouts');
        $this->loadTranslationsFrom(__DIR__.'/../../lang/cms-layouts', 'cms-layouts');
        $this->registerRoutes();

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations/layouts' => database_path('migrations'),
        ], 'cms-layouts-migrations');

        $this->publishes([
            __DIR__.'/../../config/cms-layouts.php' => config_path('cms-layouts.php'),
        ], 'cms-layouts-config');

        $this->publishes([
            __DIR__.'/../../lang/cms-layouts' => $this->app->langPath('vendor/cms-layouts'),
        ], 'cms-layouts-lang');
    }

    /**
     * Which shell a page is drawn in reaches the answer of the resolver through
     * a contributor of the pages, see {@see LayoutResolveContributor}.
     *
     * The dependency is optional on purpose: the chrome, the menus and their
     * links are complete without a tree of pages, so the layouts touch the
     * pages here and in the contributor alone, and the registration is skipped
     * where the registry of contributors is not there.
     *
     * An extender and not a `make()`, for the reason the SEO package gives:
     * the registry is a singleton, and resolving it here would either run
     * before the neighbours registered theirs or register ours twice on a
     * container that boots its providers more than once.
     */
    private function registerLayoutContributor(): void
    {
        if (! class_exists(ResolveContributorRegistry::class)) {
            return;
        }

        $this->app->extend(
            ResolveContributorRegistry::class,
            static function (ResolveContributorRegistry $registry, Application $app): ResolveContributorRegistry {
                $registry->register($app->make(LayoutResolveContributor::class));

                return $registry;
            },
        );
    }

    /**
     * What this package is called in the list of «where this file is used».
     *
     * An extender and not a `make()`, for the reason the SEO package gives:
     * the registry is a singleton, and resolving it here would either run
     * before the neighbours registered theirs or register ours twice on a
     * container that boots its providers more than once.
     */
    private function registerUsageLabels(): void
    {
        $this->app->extend(
            UsageLabelRegistry::class,
            static function (UsageLabelRegistry $registry): UsageLabelRegistry {
                $registry->register(new ChromeUsageLabelResolver());

                return $registry;
            },
        );
    }

    /**
     * The chrome is an endpoint of the package, so the package registers it.
     * The prefix and the middleware come from the configuration, which is
     * where an installation names its own stack.
     */
    private function registerRoutes(): void
    {
        $routes = $this->app->make('config')->get('cms-layouts.routes', []);

        if (! is_array($routes)) {
            return;
        }

        $prefix = $routes['prefix'] ?? 'api/v1';
        $middleware = $routes['middleware'] ?? ['api'];

        Route::group([
            'prefix' => is_string($prefix) ? $prefix : 'api/v1',
            'middleware' => is_array($middleware) || is_string($middleware) ? $middleware : ['api'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/layouts.php');
        });
    }
}
