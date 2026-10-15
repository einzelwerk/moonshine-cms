<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

use Ewk\Cms\Blocks\Exceptions\DuplicateBlockCodeException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockClassException;
use Ewk\Cms\Blocks\Exceptions\UnknownBlockCodeException;
use Ewk\Cms\Blocks\Schema\BlockSchema;

interface BlockRegistryInterface
{
    /**
     * @param  class-string  $blockClass
     *
     * @throws InvalidBlockClassException when the class does not implement {@see BlockContract}
     * @throws DuplicateBlockCodeException when another class already owns the code
     */
    public function register(string $blockClass): void;

    public function has(string $code): bool;

    /**
     * @return class-string<BlockContract>
     *
     * @throws UnknownBlockCodeException
     */
    public function classFor(string $code): string;

    /**
     * Resolve the block definition through the container, so a block type may
     * declare constructor dependencies.
     *
     * @throws UnknownBlockCodeException
     */
    public function make(string $code): BlockContract;

    /**
     * @return array<string, class-string<BlockContract>>
     */
    public function all(): array;

    /**
     * @throws UnknownBlockCodeException
     */
    public function schemaFor(string $code): BlockSchema;
}
