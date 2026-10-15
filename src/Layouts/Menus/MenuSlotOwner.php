<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Illuminate\Database\Eloquent\Model;

/**
 * The record that holds a place an installation declared. A place of the header
 * or the footer finds its owner by itself; a place of anything else, the footer
 * of a letter for one, names the record a menu put into it hangs on.
 */
interface MenuSlotOwner
{
    /**
     * The record that holds a place; null while no such record exists yet.
     */
    public function resolve(): ?Model;
}
