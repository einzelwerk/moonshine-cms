<?php

declare(strict_types=1);

return [
    /*
     * The page model of the installation. The package model is not final: an
     * application extends it to mix in what belongs to it and not to the CMS
     * (translatable captions, metadata, a factory), and names the subclass here
     * so that every page the package reads or creates is an instance of it.
     * Must extend \Ewk\Cms\Pages\Models\CmsPage and must be named so that
     * Eloquent derives `cms_pages` from it: no model of the CMS declares a
     * table, the class name is the declaration.
     */
    'model' => \Ewk\Cms\Pages\Models\CmsPage::class,

    /*
     * Address prefixes that a page may never occupy.
     *
     * Two kinds of entries live here: technical prefixes of the backend
     * (`api`, `_next`) and the non-content routes of the frontend, the ones
     * behind which there is neither a page nor an entity (`/auth`,
     * `/checkout`, `/orders`, `/dashboard`). A physical frontend route always
     * wins, simply because the resolver is never reached; a page saved under
     * such a path is not a choice but a page that silently stopped opening.
     *
     * Entries are matched as path prefixes: `auth` and `/auth` both reject
     * `/auth` and `/auth/login`. The package ships an empty list because it
     * knows neither the frontend of the application nor its deployment; the
     * consuming application publishes this file and fills it from its
     * frontend inventory.
     *
     * Language codes are NOT listed here: they come from the language catalog
     * of `ewk/moonshine-media` and are handled separately, so that a path
     * carrying a language prefix is reported as an integration error instead of
     * a plain 404.
     */
    'reserved_segments' => [],

    /*
     * The non content half of the route manifest: the named routes of the
     * frontend behind which there is neither a page nor an entity, each with
     * its path template. The content half is not repeated here — it is derived
     * from the declarations of the page types, so there is nothing to keep in
     * sync.
     *
     * This is where the `route` kind of a typed link takes its list from, so
     * that an editor picks a route out of a ready list instead of typing a
     * path:
     *
     *     'auth.login' => '/auth/login',
     *     'order' => '/orders/{uuid}',
     *
     * A placeholder of a template is a parameter of the route: a link stores
     * its values, and a link that leaves one empty is refused by the form.
     *
     * Empty by default, for the same reason the reserved segments above are:
     * the package knows neither the frontend of an installation nor its
     * deployment. The consuming application fills it from its own inventory.
     */
    'frontend_routes' => [],

    /*
     * The HTTP layer of the package: `GET <prefix>/resolve` and `GET
     * <prefix>/blocks/{uuid}`, registered by the provider of the package
     * itself. The package owns the resolver, so it owns its routes; an
     * application decides where they hang and what runs before them.
     *
     * `prefix` defaults to `api/v1`, the address the whole CMS is written
     * against. `middleware` ships the bare `api` group, because a package
     * cannot know the middleware of an installation; an application that
     * resolves route bindings, nullifies empty strings or picks the language
     * out of `Accept-Language` lists its own stack here.
     *
     * `block_throttle` is the rate limit of the endpoint of one block, in the
     * `attempts,minutes` form of Laravel. Only that endpoint is limited: it
     * answers a list that grows on the client and is therefore the cheap one
     * to hammer.
     */
    'routes' => [
        'prefix' => 'api/v1',
        'middleware' => ['api'],
        'block_throttle' => '60,1',
    ],

    /*
     * The security requirements `PageSecurityProcessor` writes on `GET
     * <prefix>/resolve` and `GET <prefix>/blocks/{uuid}`. Both render the
     * blocks of the installation, and a block may tailor its answer to the
     * visitor; an installation whose blocks do lists that identity as optional
     * with its own scheme names, `[[], ['clientUuid' => []]]`. The empty list
     * says the endpoints are public.
     */
    'openapi' => [
        'security' => [],
    ],
];
