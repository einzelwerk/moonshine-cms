<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;

/**
 * Turns the neutral icon key of the library into the markup the modal draws:
 * the server names an icon, the interface maps it onto its own set. Here that
 * set is the one MoonShine already ships, so the package grows no dependency
 * and no build step.
 *
 * The map lives in `moonshine-cms.block_icons`: a block code, or a block code
 * and a variant separated by a dot, points at the name of an icon. A variant
 * without an entry of its own falls back to the icon of its type, and a type
 * without one gets the neutral glyph.
 */
final class LibraryIcons
{
    /**
     * Drawn for a code nobody declared an icon for: a shape that says «a
     * block» and nothing more.
     */
    private const NEUTRAL = 'squares-2x2';

    /** @var array<string, string> */
    private array $map;

    /** @var array<string, string> rendered markup per icon name */
    private array $rendered = [];

    public function __construct(private readonly Factory $views, Repository $config)
    {
        /** @var mixed $configured */
        $configured = $config->get('moonshine-cms.block_icons', []);

        $this->map = [];

        if (is_array($configured)) {
            foreach ($configured as $key => $name) {
                if (is_string($key) && is_string($name)) {
                    $this->map[$key] = $name;
                }
            }
        }
    }

    public function forType(string $code): string
    {
        return $this->markup($this->map[$code] ?? self::NEUTRAL);
    }

    public function forVariant(string $code, string $variant): string
    {
        return $this->markup(
            $this->map[$code.'.'.$variant] ?? $this->map[$code] ?? self::NEUTRAL,
        );
    }

    /**
     * A name the set does not hold falls back to the neutral glyph: a wrong
     * entry in the configuration leaves a card without its picture, never
     * without a picture at all.
     */
    private function markup(string $name): string
    {
        if (isset($this->rendered[$name])) {
            return $this->rendered[$name];
        }

        $view = 'moonshine::icons.'.$name;

        if (! $this->views->exists($view)) {
            return $this->rendered[$name] = $name === self::NEUTRAL ? '' : $this->markup(self::NEUTRAL);
        }

        return $this->rendered[$name] = trim($this->views->make($view)->render());
    }
}
