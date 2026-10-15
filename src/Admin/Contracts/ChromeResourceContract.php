<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Contracts;

use Ewk\Cms\Layouts\Chrome\ChromeKind;

/**
 * A resource of a header or of a footer: the two are twins and share their
 * pages, which learn which kind they edit from the resource.
 */
interface ChromeResourceContract
{
    public function chromeKind(): ChromeKind;
}
