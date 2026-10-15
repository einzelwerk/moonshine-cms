<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Contracts;

/**
 * A record whose name alone does not tell it apart from its siblings: the
 * shows of one tour are all called the same. The entity picker draws the
 * detail in brackets after the name, «Mein Tag (12.10.2026 19:00)».
 */
interface PickerDetailContract
{
    /**
     * `null` or an empty string where the record has nothing to add.
     */
    public function pickerDetail(): ?string;
}
