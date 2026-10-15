<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Concerns;

use Ewk\Cms\Blocks\Media\ContentMediaUsages;
use Ewk\Cms\Layouts\Chrome\ChromeContent;
use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchema;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Illuminate\Container\Container;

/**
 * Shared behaviour of the header and the footer: their `content` is a bag of
 * values described by the declared fields of their kind.
 *
 * Normalization happens on every write and not only in the admin form, for
 * the same reason block placement is checked on the model: a rule that lives
 * in one form only is bypassed by the first import or seeder.
 *
 * An installation that has declared no schema for the kind keeps its stored
 * content untouched, exactly as a block of an unregistered type does.
 */
trait HasChromeContent
{
    abstract public function chromeKind(): ChromeKind;

    public static function bootHasChromeContent(): void
    {
        static::saving(static function (self $model): void {
            $model->setAttribute('content', $model->normalizedChromeContent());
        });

        // A header and a footer hold media the same way a block does — a logo,
        // payment icons — so they are counted the same way. The owner of the
        // usage is this record and not the layout above it: a layout points at
        // a header, it does not hold its pictures.
        static::saved(static function (self $model): void {
            $model->syncChromeMediaUsages();
        });

        static::deleted(static function (self $model): void {
            $model->releaseChromeMediaUsages();
        });
    }

    /**
     * The name usages of a header or a footer are grouped under. One bag of
     * content, one group; the dotted path inside it tells two pictures apart.
     */
    public static function chromeMediaUsageField(): string
    {
        return 'content';
    }

    public function chromeSchema(): ChromeSchema
    {
        $container = Container::getInstance();

        if (! $container->bound(ChromeSchemaRegistry::class)) {
            return ChromeSchema::make();
        }

        return $container->make(ChromeSchemaRegistry::class)->schemaFor($this->chromeKind());
    }

    /**
     * A kind nothing declared a schema for is left alone, exactly as a block of
     * an unregistered type is, see {@see \Ewk\Cms\Blocks\Models\CmsBlock}: without
     * a schema the content is an opaque bag, and rewriting the usages of an
     * opaque bag would release the files the header still shows.
     */
    private function syncChromeMediaUsages(): void
    {
        $container = Container::getInstance();

        if (! $container->bound(ContentMediaUsages::class) || ! $container->bound(ChromeSchemaRegistry::class)) {
            return;
        }

        $registry = $container->make(ChromeSchemaRegistry::class);
        $kind = $this->chromeKind();

        if (! $registry->has($kind)) {
            return;
        }

        $content = $this->getAttribute('content');

        $container->make(ContentMediaUsages::class)->sync(
            $this,
            self::chromeMediaUsageField(),
            is_array($content) ? $content : null,
            $registry->schemaFor($kind)->fields(),
        );
    }

    private function releaseChromeMediaUsages(): void
    {
        $container = Container::getInstance();

        if (! $container->bound(ContentMediaUsages::class)) {
            return;
        }

        $container->make(ContentMediaUsages::class)->release($this);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function normalizedChromeContent(): ?array
    {
        $content = $this->getAttribute('content');

        if (! is_array($content)) {
            return null;
        }

        $container = Container::getInstance();

        if (! $container->bound(ChromeSchemaRegistry::class)) {
            return $content;
        }

        $registry = $container->make(ChromeSchemaRegistry::class);
        $kind = $this->chromeKind();

        if (! $registry->has($kind)) {
            return $content;
        }

        return (new ChromeContent())->normalize($content, $registry->schemaFor($kind));
    }
}
