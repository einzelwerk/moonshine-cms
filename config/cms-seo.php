<?php

declare(strict_types=1);

return [
    /*
     * The address of the public site. Metadata travels into tags of the
     * frontend, so the addresses in it are absolute and carry the language —
     * the relative `href` of a block is a different thing and stays relative.
     *
     * The backend and the site are different hosts in a headless setup, which
     * is why this is its own setting and not `app.url`; `app.url` is the
     * fallback so a single-host installation needs no configuration at all.
     */
    'site_url' => env('CMS_SITE_URL', env('APP_URL')),

    /*
     * How the language appears in an address: `always` puts a prefix on every
     * language, `never` puts none at all. `always` is the default of
     * next-intl and therefore ours.
     *
     * A single-language site without a prefix sets `never`; there is no
     * "prefix every language but the default" mode, because it would make the
     * language versions of one page differ in shape, and that is exactly what
     * the alternates set is supposed to make uniform.
     */
    'locale_prefix' => 'always',

    /*
     * The media preset used for the Open Graph picture. `null` means the
     * original file. A preset that exists but is not generated yet also
     * yields the original: a broken address in a tag is worse than a large
     * picture.
     *
     * The core ships no preset of its own — sizes are a matter of the
     * installation.
     */
    'image_preset' => null,

    /*
     * The sitemap feed: `GET <prefix>/sitemap` names the files, `GET
     * <prefix>/sitemap/{id}` answers the entries of one, and the frontend
     * writes the XML.
     *
     * `per_file` is how many entries one file holds; the protocol allows
     * 50 000 and a larger value is cut to that. A file holds one language, so
     * this is also the number of records of a source in one file.
     *
     * `routes` follows `cms-pages.routes`: the package owns the endpoints,
     * an application decides where they hang and what runs before them. An
     * installation that sets this key sets the whole array — the merge of a
     * package config is shallow.
     */
    'sitemap' => [
        'per_file' => 10000,
        'routes' => [
            'prefix' => 'api/v1',
            'middleware' => ['api'],
        ],
    ],
];
