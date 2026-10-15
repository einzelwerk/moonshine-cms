<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Ewk\Cms\Pages\Models\CmsPage as BaseCmsPage;

/**
 * What an application does with the page model: extends it with its own
 * behaviour and names the subclass in `cms-pages.model`.
 *
 * The subclass repeats the name of the base class, because no model of the CMS
 * declares a table and Eloquent derives `cms_pages` from the name of the class
 * that is actually instantiated.
 */
final class CmsPage extends BaseCmsPage
{
    public function caption(): string
    {
        return (string) ($this->name['ru'] ?? '');
    }
}
