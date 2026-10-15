<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tree;

/**
 * One template type as the «Pages» section shows it.
 *
 * A template is not a row an editor creates: the set of types is code. So it
 * is neither a node of the tree nor a section of its own, but a tile under the
 * tree that says what the template stands for, whether it is assembled and
 * where its blocks are.
 */
final readonly class TemplateTile
{
    public function __construct(
        public string $type,
        public string $title,
        /** What the template stands for and what its addresses look like. */
        public string $description,
        public string $status,
        /** The tone of the status badge, in the names of the panel. */
        public string $tone,
        /** The block editor of the assembled template, empty while there is none. */
        public string $editorUrl,
        /** The address that assembles it, empty once it is assembled. */
        public string $assembleUrl,
    ) {
    }
}
