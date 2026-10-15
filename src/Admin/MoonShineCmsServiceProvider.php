<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\MoonShine\Library\UsageLinkRegistry;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\Adapters\FieldAdapter;
use Ewk\MoonShineCms\MoonShine\Resources\FooterResource;
use Ewk\MoonShineCms\MoonShine\Resources\HeaderResource;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Fields\Textarea;

final class MoonShineCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/moonshine-cms.php', 'moonshine-cms');

        $this->app->singleton(FieldAdapter::class, function (Application $app): FieldAdapter {
            $config = $app->make(Repository::class);

            return new FieldAdapter(
                $app->make(LanguageCatalog::class),
                $this->richTextField($config->get('moonshine-cms.rich_text_field')),
                Config::integer('moonshine-cms.entity_options_limit', 100),
            );
        });

        // Whoever asks the container for the page resource gets the one of
        // the installation, see MoonShineCms::pageResource(): the templates
        // page and the link picker address it by the package class.
        $this->app->bind(PageResource::class, static function (Application $app): PageResource {
            $configured = MoonShineCms::pageResource($app->make(Repository::class));

            $resource = $configured === PageResource::class
                ? $app->build(PageResource::class)
                : $app->make($configured);

            // A binding of the installation answers with whatever its
            // closure returns.
            if (! $resource instanceof PageResource) {
                throw new LogicException(sprintf('The binding of [%s] must resolve to a page resource.', $configured));
            }

            return $resource;
        });

        $this->registerUsageLinks();
    }

    public function boot(CoreContract $core): void
    {
        // The sections of the CMS: the core merges what every provider
        // registers, so the panel of the application keeps its own resources
        // next to these.
        $core->resources(MoonShineCms::resources($this->app->make(Repository::class)));

        $this->loadTranslationsFrom(__DIR__.'/../../lang/moonshine-cms', 'moonshine-cms');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'moonshine-cms');
        $this->registerRoutes();

        $this->publishes([
            __DIR__.'/../../config/moonshine-cms.php' => config_path('moonshine-cms.php'),
        ], 'moonshine-cms-config');

        $this->publishes([
            __DIR__.'/../../lang/moonshine-cms' => $this->app->langPath('vendor/moonshine-cms'),
        ], 'moonshine-cms-lang');

        $this->publishes([
            __DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/moonshine-cms'),
        ], 'moonshine-cms-views');
    }

    /**
     * Where «used in» of the media library links a record of the CMS and what
     * it calls a usage of one. The registry belongs to the admin of
     * `ewk/moonshine-media` and is bound by its provider, which an
     * application may register before or after this one, so the entries are
     * added by an extender: it runs whenever the registry is first resolved,
     * whichever provider came first.
     *
     * The page resource is a closure because it is a matter of
     * configuration, see {@see MoonShineCms::pageResource()}, and is read
     * when a link is built.
     */
    private function registerUsageLinks(): void
    {
        $this->app->extend(
            UsageLinkRegistry::class,
            static fn (UsageLinkRegistry $links, Application $app): UsageLinkRegistry => $links
                ->resource(CmsPage::MORPH_ALIAS, static fn (): string => MoonShineCms::pageResource($app->make(Repository::class)))
                ->resource(CmsSharedBlock::MORPH_ALIAS, SharedBlockResource::class)
                ->resource(CmsHeader::MORPH_ALIAS, HeaderResource::class)
                ->resource(CmsFooter::MORPH_ALIAS, FooterResource::class)
                ->caption('page', 'moonshine-cms::usages.page')
                ->caption('shared_block', 'moonshine-cms::usages.shared_block')
                ->caption('header', 'moonshine-cms::usages.header')
                ->caption('footer', 'moonshine-cms::usages.footer')
                ->caption('metadata', 'moonshine-cms::usages.metadata'),
        );
    }

    /**
     * The lookups of the link picker hang inside the panel: same domain, same
     * prefix, same middleware, plus the authentication of the panel, see
     * {@see \Ewk\MoonShineCms\Http\Controllers\LinkPickerController}. An
     * installation that switched the routes of MoonShine off gets none of
     * these either.
     */
    private function registerRoutes(): void
    {
        $configurator = $this->app->make(ConfiguratorContract::class);

        if (! $configurator->isUseRoutes()) {
            return;
        }

        $declared = $configurator->getDefaultRouteGroup();

        $group = array_filter([
            'domain' => $declared['domain'] ?? null,
            'prefix' => $declared['prefix'] ?? null,
            'as' => $declared['as'] ?? null,
        ]) + ['middleware' => ['moonshine', ...$configurator->getAuthMiddleware()]];

        Route::group($group, function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/moonshine.php');
        });
    }

    /**
     * MoonShine 4.19 ships no rich text editor, so the class is configurable.
     * A value that is not a MoonShine field would fail deep inside the form,
     * so it is refused here.
     *
     * @return class-string<Field>
     */
    private function richTextField(mixed $configured): string
    {
        if (! is_string($configured) || ! is_subclass_of($configured, Field::class)) {
            return Textarea::class;
        }

        return $configured;
    }
}
