<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The slots this installation knows: the dictionary of {@see MenuSlot}, plus
 * whatever `cms-layouts.menu_slots` adds, plus the places the installation
 * declared in code together with their owner.
 *
 * The configured entries extend the dictionary, they do not replace it: the
 * shipped slots are places the frontend of the product already draws, and an
 * installation that adds a place of its own does not thereby remove them.
 *
 * The first two groups are the places of the site chrome, {@see chromeCodes()};
 * a declared place ({@see MenuSlotDefinition}) belongs to a record of the
 * installation, the footer of a letter for one, and never reaches the chrome
 * document nor its OpenAPI description. An installation declares one through
 * `$this->app->extend(MenuSlotCatalog::class, ...)`.
 */
final class MenuSlotCatalog
{
    /** @var array<string, MenuSlotDefinition> */
    private array $definitions = [];

    public function __construct(private readonly Repository $config)
    {
    }

    /**
     * @throws InvalidArgumentException when the code is already a place of this installation: two owners of one place would make the owner of a menu put there a guess
     */
    public function register(MenuSlotDefinition $definition): void
    {
        $code = $definition->code();

        if (in_array($code, $this->chromeCodes(), true) || isset($this->definitions[$code])) {
            throw new InvalidArgumentException(sprintf('The menu slot [%s] is already known.', $code));
        }

        $this->definitions[$code] = $definition;
    }

    /**
     * Every place a menu may be put into: the chrome ones first, then the
     * declared ones in the order of registration.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = $this->chromeCodes();

        foreach (array_keys($this->definitions) as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * The places of the header and of the footer only: the shipped dictionary
     * plus the configured entries. This is what the chrome document delivers
     * and what its OpenAPI description enumerates.
     *
     * @return list<string>
     */
    public function chromeCodes(): array
    {
        $codes = MenuSlot::codes();

        foreach ($this->configured() as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public function has(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    public function isChromeSlot(string $code): bool
    {
        return in_array($code, $this->chromeCodes(), true);
    }

    /**
     * Which part of the chrome owns the place, see {@see MenuSlot::kind()}. A
     * slot an installation added through the configuration says nothing about
     * its owner, so the answer is null and such a place is filled on the form
     * of the header or of the footer itself. A declared place is no part of the
     * chrome either, so it is null as well; its owner is {@see ownerOf()}.
     */
    public function kindOf(string $code): ?ChromeKind
    {
        return MenuSlot::tryFrom($code)?->kind();
    }

    /**
     * Whether the form of a menu may offer the place: its owner is known
     * without asking the editor, from the enum or from the declaration.
     */
    public function isAssignable(string $code): bool
    {
        return $this->kindOf($code) instanceof ChromeKind || isset($this->definitions[$code]);
    }

    /**
     * The caption a declaration gave; null for every other place, whose words
     * live in the translations of the admin panel.
     */
    public function labelOf(string $code): ?string
    {
        return isset($this->definitions[$code]) ? $this->definitions[$code]->caption() : null;
    }

    /**
     * The record a menu put into the place hangs on.
     *
     * A declared place answers with its declared owner. A place of the header
     * or the footer answers with the record that already fills it, and
     * otherwise with the first active record of its kind, then with the first
     * one at all: an installation with several headers arranges the rare case
     * on the form of the header itself, where the whole table is edited. A
     * configured place has no owner to read off anything, so null.
     */
    public function ownerOf(string $code): ?Model
    {
        if (isset($this->definitions[$code])) {
            return $this->definitions[$code]->owner();
        }

        $kind = $this->kindOf($code);

        if (! $kind instanceof ChromeKind) {
            return null;
        }

        // A switched off row counts too: the place is already taken by that
        // record, and a second owner of it would be a surprise once the row is
        // switched back on.
        $neighbour = CmsMenuSlotAssignment::query()
            ->where('slot', $code)
            ->orderBy('id')
            ->first();

        if ($neighbour instanceof CmsMenuSlotAssignment && $neighbour->owner instanceof Model) {
            return $neighbour->owner;
        }

        $model = $kind === ChromeKind::HEADER ? CmsHeader::class : CmsFooter::class;

        return $model::query()->where('is_active', true)->orderBy('id')->first()
            ?? $model::query()->orderBy('id')->first();
    }

    /**
     * @return list<MenuSlotDefinition>
     */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    /**
     * @return list<string>
     */
    private function configured(): array
    {
        $configured = $this->config->get('cms-layouts.menu_slots', []);

        if (! is_array($configured)) {
            return [];
        }

        $codes = [];

        foreach ($configured as $code) {
            if (is_string($code) && trim($code) !== '') {
                $codes[] = trim($code);
            }
        }

        return $codes;
    }
}
