<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Links;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The addresses the link picker asks while the editor is choosing, see
 * {@see \Ewk\MoonShineCms\Http\Controllers\LinkPickerController}.
 *
 * A panel whose routes are switched off gives none, and the picker then draws
 * what it already has instead of pointing the browser at an address that
 * answers the login page.
 */
final readonly class LinkEndpoints
{
    /**
     * The name the routes of the package carry inside the group of the panel.
     */
    private const string PREFIX = 'moonshine.cms.link.';

    /** @var list<string> */
    private const array LOOKUPS = ['pages', 'entities', 'anchors', 'catalog'];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $urls = [];

        foreach (self::LOOKUPS as $lookup) {
            $url = self::url($lookup);

            if ($url !== null) {
                $urls[$lookup] = $url;
            }
        }

        return $urls;
    }

    public static function url(string $lookup): ?string
    {
        $name = self::PREFIX.$lookup;

        try {
            return Route::has($name) ? route($name) : null;
        } catch (Throwable) {
            // Outside a routed application — a console command building a
            // schema, a test of the adapter — there is nothing to ask.
            return null;
        }
    }
}
