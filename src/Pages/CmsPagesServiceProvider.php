<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry;
use Ewk\Cms\Pages\Links\EntityLinkResolver;
use Ewk\Cms\Pages\Links\ListLinkResolver;
use Ewk\Cms\Pages\Links\PageLinkResolver;
use Ewk\Cms\Pages\Links\PageLookup;
use Ewk\Cms\Pages\Links\RouteLinkResolver;
use Ewk\Cms\Pages\Media\PageUsageLabelResolver;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CmsPagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cms-pages.php', 'cms-pages');

        // Page types are declared in code and registered by the application: a
        // declaration names a model class and a path template, which are things
        // this package cannot know.
        $this->app->singleton(PageTypeRegistry::class);

        // Metadata and JSON-LD belong to the answer of the resolver, but the
        // pages may not depend on the SEO part: whoever wants to write into the
        // document registers a contributor here.
        $this->app->singleton(ResolveContributorRegistry::class);

        // What the links of one request read about pages, see PageLookup:
        // scoped and never a singleton, so a worker of Octane starts every
        // request from the database.
        $this->app->scoped(PageLookup::class);

        // Four kinds of link are addresses this package owns, so all four are
        // wired here and not in every installation: a page, a catalog with
        // filters, an entity of a template type — whose address comes out of
        // the very same path template — and a named route of the frontend,
        // whose manifest is a configuration of this package.
        $this->app->extend(
            LinkResolverRegistry::class,
            static function (LinkResolverRegistry $resolvers, Container $app): LinkResolverRegistry {
                $resolvers->register($app->make(PageLinkResolver::class));
                $resolvers->register($app->make(ListLinkResolver::class));
                $resolvers->register($app->make(EntityLinkResolver::class));
                $resolvers->register($app->make(RouteLinkResolver::class));

                return $resolvers;
            },
        );
    }

    public function boot(): void
    {
        // A page writes one alias into every polymorphic column, see
        // CmsPage::MORPH_ALIAS; reading it back has to produce the class of the
        // installation, which is the subclass named in the configuration.
        Relation::morphMap([CmsPage::MORPH_ALIAS => PageModel::class()]);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/pages');
        $this->loadTranslationsFrom(__DIR__.'/../../lang/cms-pages', 'cms-pages');
        $this->registerRoutes();

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations/pages' => database_path('migrations'),
        ], 'cms-pages-migrations');

        $this->publishes([
            __DIR__.'/../../config/cms-pages.php' => config_path('cms-pages.php'),
        ], 'cms-pages-config');

        $this->publishes([
            __DIR__.'/../../lang/cms-pages' => $this->app->langPath('vendor/cms-pages'),
        ], 'cms-pages-lang');

        $this->registerUsageLabels();
        $this->forgetLookupOnWrites();
    }

    /**
     * A page or a block written in the middle of a request — the admin saves
     * and renders again, a test switches a page off between two calls of one
     * application — must not be answered from what {@see PageLookup} read
     * before the write. Every write of the CMS goes through a model, see
     * {@see \Ewk\Cms\Pages\Services\PageTree}, so the model events are
     * enough; the only mass update, the order of blocks, changes nothing a
     * link reads.
     *
     * The lookup is taken from the container of the moment, never captured:
     * under Octane that is the sandbox of the request doing the write.
     */
    private function forgetLookupOnWrites(): void
    {
        $events = [];

        // The class of the installation fires the events of its rows; the
        // package class is heard as well, for a configuration that names
        // another class only after the provider booted.
        foreach (array_unique([PageModel::class(), CmsPage::class, CmsBlock::class]) as $model) {
            foreach (['saved', 'deleted'] as $event) {
                $events[] = 'eloquent.'.$event.': '.$model;
            }
        }

        $this->app->make(Dispatcher::class)->listen($events, static function (): void {
            PageLookup::current()->forget();
        });
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
                $registry->register(new PageUsageLabelResolver());

                return $registry;
            },
        );
    }

    /**
     * The resolver is an endpoint of the package, so the package registers
     * it. The prefix and the middleware come from the configuration, which is
     * where an installation names its own stack.
     */
    private function registerRoutes(): void
    {
        $routes = $this->app->make('config')->get('cms-pages.routes', []);

        if (! is_array($routes)) {
            return;
        }

        $prefix = $routes['prefix'] ?? 'api/v1';
        $middleware = $routes['middleware'] ?? ['api'];

        Route::group([
            'prefix' => is_string($prefix) ? $prefix : 'api/v1',
            'middleware' => is_array($middleware) || is_string($middleware) ? $middleware : ['api'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/pages.php');
        });
    }
}
