<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockClassException;
use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\Resolvers\ActionLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\AnchorLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\EmailLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\FileLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\PhoneLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\UrlLinkResolver;
use Ewk\Cms\Blocks\Media\BlockUsageLabelResolver;
use Ewk\Cms\Blocks\Media\ContentMediaUsages;
use Ewk\Cms\Blocks\Media\MediaReferenceCollector;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Nesting\PlacementValidator;
use Ewk\Cms\Blocks\Params\ParameterResolution;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

final class CmsBlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cms-blocks.php', 'cms-blocks');

        $this->app->singleton(BlockRegistryInterface::class, BlockRegistry::class);

        // Bound by name and not left to autowiring: the model asks the
        // container whether the accounting is there at all, and an unbound
        // concrete class answers «no» to that question.
        $this->app->singleton(MediaReferenceCollector::class);
        $this->app->singleton(ContentMediaUsages::class);

        $this->app->singleton(PlacementValidator::class, function (Application $app): PlacementValidator {
            $maxDepth = $app->make(Repository::class)->get('cms-blocks.max_depth', 2);

            return new PlacementValidator(
                $app->make(BlockRegistryInterface::class),
                is_int($maxDepth) ? $maxDepth : 2,
            );
        });

        $this->app->singleton(ParameterResolution::class, function (Application $app): ParameterResolution {
            $config = $app->make(Repository::class);

            return new ParameterResolution(
                $app->make(BlockRegistryInterface::class),
                $app->make(ValidationFactory::class),
                self::limit($config, 'default_per_page', 10),
                self::limit($config, 'max_per_page', 100),
                self::limit($config, 'max_shown', 500),
            );
        });

        // Actions are a registry of their own, like blocks and field types: the
        // core ships none, because the address of an action is built out of the
        // entities of an installation.
        $this->app->singleton(ActionRegistry::class);

        $this->app->singleton(LinkResolverRegistry::class, static function (Application $app): LinkResolverRegistry {
            $resolvers = new LinkResolverRegistry();

            $resolvers->register(new UrlLinkResolver());
            $resolvers->register(new EmailLinkResolver());
            $resolvers->register(new PhoneLinkResolver());
            $resolvers->register(new AnchorLinkResolver($app));
            $resolvers->register(new FileLinkResolver($app->make(Repository::class)));
            $resolvers->register(new ActionLinkResolver($app->make(ActionRegistry::class)));

            return $resolvers;
        });
    }

    public function boot(): void
    {
        // The polymorphic columns of the CMS carry aliases and not class
        // names, so that a model may be renamed without a data migration.
        Relation::morphMap([
            CmsBlock::MORPH_ALIAS => CmsBlock::class,
            CmsSharedBlock::MORPH_ALIAS => CmsSharedBlock::class,
            CmsVersion::MORPH_ALIAS => CmsVersion::class,
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/blocks');
        $this->loadTranslationsFrom(__DIR__.'/../../lang/cms-blocks', 'cms-blocks');

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations/blocks' => database_path('migrations'),
        ], 'cms-blocks-migrations');

        $this->publishes([
            __DIR__.'/../../config/cms-blocks.php' => config_path('cms-blocks.php'),
        ], 'cms-blocks-config');

        $this->publishes([
            __DIR__.'/../../lang/cms-blocks' => $this->app->langPath('vendor/cms-blocks'),
        ], 'cms-blocks-lang');

        $this->registerBlocks();
        $this->registerUsageLabels();
    }

    /**
     * Who the library is told a block belongs to.
     *
     * An extender and not a `make()` in `boot()`, for the reason the SEO
     * package gives: the registry is a singleton, and resolving it here would
     * either run before the neighbours had registered theirs or register ours
     * twice on a container that boots its providers more than once.
     *
     * The resolver is built by hand and not resolved out of the container:
     * it takes the very registry that is being extended, and asking for it
     * again from inside its own extender would resolve it a second time.
     */
    private function registerUsageLabels(): void
    {
        $this->app->extend(
            UsageLabelRegistry::class,
            static function (UsageLabelRegistry $registry): UsageLabelRegistry {
                $registry->register(new BlockUsageLabelResolver($registry));

                return $registry;
            },
        );
    }

    private static function limit(Repository $config, string $key, int $fallback): int
    {
        $value = $config->get('cms-blocks.pagination.'.$key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }

    private function registerBlocks(): void
    {
        $registry = $this->app->make(BlockRegistryInterface::class);
        $blocks = $this->app->make(Repository::class)->get('cms-blocks.blocks', []);

        if (! is_array($blocks)) {
            return;
        }

        foreach ($blocks as $blockClass) {
            if (! is_string($blockClass) || ! class_exists($blockClass)) {
                throw InvalidBlockClassException::forClass(
                    is_string($blockClass) ? $blockClass : get_debug_type($blockClass),
                );
            }

            $registry->register($blockClass);
        }
    }
}
