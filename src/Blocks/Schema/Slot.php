<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Schema;

use InvalidArgumentException;

/**
 * One named place inside a container. Columns, tabs and an accordion are
 * containers with a different number of slots, not different mechanisms.
 *
 * Every restriction is declared as data and checked on the server, both when a
 * block is created and when it is moved: a rule that lives only in the form is
 * bypassed with a mouse.
 */
final readonly class Slot
{
    /**
     * @param  list<string>|string  $allow  block codes this slot accepts, or '*' for any
     * @param  int  $min  children required at publication; an empty slot may be saved in a draft
     * @param  int|null  $max  children the slot accepts, null for unlimited
     * @param  int  $span  width of the slot on the twelve column grid
     * @param  bool  $uniqueTypes  forbid a second child of the same type in this slot
     */
    public function __construct(
        public string $name,
        public string $title,
        public array|string $allow = '*',
        public int $min = 0,
        public ?int $max = null,
        public int $span = 12,
        public bool $uniqueTypes = false,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('A slot needs a name.');
        }

        if ($span < 1 || $span > 12) {
            throw new InvalidArgumentException("Slot [{$name}] span must sit between 1 and 12, got {$span}.");
        }

        if ($min < 0) {
            throw new InvalidArgumentException("Slot [{$name}] cannot require a negative number of children.");
        }

        if ($max !== null && $max < $min) {
            throw new InvalidArgumentException("Slot [{$name}] declares a maximum below its minimum.");
        }
    }

    /**
     * The white list of the slot. Combined with the block type's own list of
     * allowed parents through AND, never OR: a block declaring itself a child
     * of a container must not slip past the container's white list.
     */
    public function accepts(string $type): bool
    {
        if ($this->allow === '*') {
            return true;
        }

        return is_array($this->allow) && in_array($type, $this->allow, true);
    }
}
