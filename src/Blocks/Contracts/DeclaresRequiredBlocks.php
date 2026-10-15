<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

/**
 * An owner that requires some block types to be in place. A page declares them
 * through its page type; this package only asks the owner for the codes, so the
 * core keeps knowing nothing about page types.
 *
 * Publication reports the missing ones.
 */
interface DeclaresRequiredBlocks
{
    /**
     * Block codes that must be present and active before publication.
     *
     * @return list<string>
     */
    public function requiredBlockCodes(): array;
}
