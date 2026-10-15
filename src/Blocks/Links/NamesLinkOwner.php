<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

use Illuminate\Database\Eloquent\Model;

/**
 * A resolver that can also name the record its target stands for.
 *
 * It exists for one reason: the resolver of the `anchor` kind lives in this
 * package and has to look at the blocks of the page an editor pointed at, while
 * what a page is belongs to the pages part. Blocks are already addressed by
 * their owner here, so naming the owner is the whole of the contract; the
 * caller takes it from there.
 *
 * Reachability is not the question: a record that is switched off or that
 * holds no address of its own is still the record the link names.
 */
interface NamesLinkOwner
{
    /**
     * @param  array<string, mixed>  $attributes  target identifiers of the link
     */
    public function ownerOf(array $attributes): ?Model;
}
