<?php

declare(strict_types=1);

return [
    /*
     * Extra menu slots of this installation.
     *
     * A slot is a place in the markup of the frontend, which is why the
     * dictionary is closed and travels into the API description as an
     * enumeration: an open list would oblige the frontend to draw a place it
     * does not know. The shipped slots — `header_primary`,
     * `header_secondary`, `footer_1`, `footer_2`, `footer_3`, `legal`,
     * `social` — are always known; entries listed here are added to them, and
     * each one needs a place in the frontend before it is of any use.
     */
    'menu_slots' => [],

    /*
     * The endpoints of the chrome: `GET <prefix>/chrome` and `GET
     * <prefix>/menus/{code}`, registered by the provider of this package. The
     * package owns the chrome, so it owns its routes; an application decides
     * where they hang and what runs before them.
     *
     * `middleware` ships the bare `api` group, because a package cannot know
     * the middleware of an installation; an application that resolves route
     * bindings, nullifies empty strings or picks the language out of
     * `Accept-Language` lists its own stack here.
     */
    'routes' => [
        'prefix' => 'api/v1',
        'middleware' => ['api'],
    ],
];
