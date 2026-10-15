<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Illuminate\Http\Request;

/**
 * An addition to the answer of the resolver made by another part of the CMS or
 * by the application.
 *
 * The pages may not depend on the SEO part or on the layouts, yet metadata and
 * JSON-LD belong to the very same answer. So the resolver builds its own body
 * and then lets whoever registered a contributor write into the document.
 */
interface ResolveContributor
{
    public function contribute(ResolveDocument $document, Request $request): void;
}
