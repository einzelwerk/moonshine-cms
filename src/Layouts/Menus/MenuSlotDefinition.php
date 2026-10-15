<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A place for a menu that an installation declares together with the record
 * holding it.
 *
 * The header and the footer are not the only things with menus: a letter has
 * a legal row and a row of networks under it, and an editor chooses the menus
 * for them on the form of the menu exactly as for a column of the footer. A
 * place listed in `cms-layouts.menu_slots` cannot do that, because nothing
 * says what a menu put there hangs on; a definition says it.
 *
 * Such a place is not a place of the site chrome: it never travels into the
 * chrome document nor into its OpenAPI description, see
 * {@see MenuSlotCatalog::chromeCodes()}.
 *
 * The caption and the owner are closures on purpose. A definition is
 * registered while the application boots, before the locale of a request is
 * known and possibly before the table of the owner exists, so both are asked
 * at the moment they are needed.
 */
final class MenuSlotDefinition
{
    private string|Closure|null $label = null;

    private MenuSlotOwner|Closure|null $owner = null;

    private function __construct(private readonly string $code)
    {
    }

    /**
     * @throws InvalidArgumentException when the code is empty
     */
    public static function make(string $code): self
    {
        $code = trim($code);

        if ($code === '') {
            throw new InvalidArgumentException('A menu slot needs a code.');
        }

        return new self($code);
    }

    /**
     * @param  string|Closure(): string  $label  a closure is called on every read, so a translation follows the locale of the request
     */
    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  MenuSlotOwner|Closure(): ?Model  $owner
     */
    public function ownedBy(MenuSlotOwner|Closure $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * How an editor reads the place; the code when the installation gave no
     * words for it, the same fallback the panel uses for a configured place.
     */
    public function caption(): string
    {
        $label = $this->label instanceof Closure ? ($this->label)() : $this->label;

        return is_string($label) && $label !== '' ? $label : $this->code;
    }

    /**
     * The record a menu put into this place hangs on; null when no owner was
     * declared or the declared one does not exist yet.
     */
    public function owner(): ?Model
    {
        $owner = $this->owner instanceof MenuSlotOwner ? $this->owner->resolve() : ($this->owner instanceof Closure ? ($this->owner)() : null);

        return $owner instanceof Model ? $owner : null;
    }
}
