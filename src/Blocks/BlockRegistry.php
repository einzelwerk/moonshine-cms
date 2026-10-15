<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks;

use Ewk\Cms\Blocks\Contracts\BlockContract;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Exceptions\DuplicateBlockCodeException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockClassException;
use Ewk\Cms\Blocks\Exceptions\UnknownBlockCodeException;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Illuminate\Contracts\Container\Container;

/**
 * Registration is an explicit list from configuration, never a filesystem
 * scan, so the set of block types is visible in review and in tests.
 */
final class BlockRegistry implements BlockRegistryInterface
{
    /** @var array<string, class-string<BlockContract>> */
    private array $blocks = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(string $blockClass): void
    {
        if (! is_subclass_of($blockClass, BlockContract::class)) {
            throw InvalidBlockClassException::forClass($blockClass);
        }

        $code = $blockClass::code();
        $existing = $this->blocks[$code] ?? null;

        if ($existing !== null && $existing !== $blockClass) {
            throw DuplicateBlockCodeException::forCode($code, $existing, $blockClass);
        }

        $this->blocks[$code] = $blockClass;
    }

    public function has(string $code): bool
    {
        return isset($this->blocks[$code]);
    }

    public function classFor(string $code): string
    {
        return $this->blocks[$code] ?? throw UnknownBlockCodeException::forCode($code);
    }

    public function make(string $code): BlockContract
    {
        $class = $this->classFor($code);
        $block = $this->container->make($class);

        // The container may hold a binding for the class, and a binding
        // answers with whatever its closure returns.
        if (! $block instanceof BlockContract) {
            throw InvalidBlockClassException::forClass($class);
        }

        return $block;
    }

    public function all(): array
    {
        return $this->blocks;
    }

    public function schemaFor(string $code): BlockSchema
    {
        return $this->make($code)->schema();
    }
}
