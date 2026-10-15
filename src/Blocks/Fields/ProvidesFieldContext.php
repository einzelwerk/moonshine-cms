<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

/**
 * An owner of blocks that can describe itself to the fields of its cards, see
 * {@see FieldContext}. A page of `cms-pages` is described by its type, and the
 * admin derives that on its own; an owner of another kind — an entity that
 * carries blocks directly — answers for itself by implementing this.
 *
 * The contract lives here and not next to the owner for the reason the whole
 * CMS is built on: the pages depend on the blocks and never the other way
 * round.
 */
interface ProvidesFieldContext
{
    public function blockFieldContext(): FieldContext;
}
