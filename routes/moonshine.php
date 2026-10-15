<?php

declare(strict_types=1);

use Ewk\MoonShineCms\Http\Controllers\LinkPickerController;
use Illuminate\Support\Facades\Route;

/*
 * The lookups of the link picker. They belong to the package and not to a
 * resource: the picker is drawn on the block editor, on a menu item and on
 * the header alike, and none of those owns the others.
 *
 * The group carries the prefix, the domain and the middleware of the panel,
 * plus its authentication: the answers name pages, records and files of the
 * installation and are meant for an administrator.
 */

Route::controller(LinkPickerController::class)
    ->prefix('cms/link')
    ->as('cms.link.')
    ->group(static function (): void {
        Route::get('pages', 'pages')->name('pages');
        Route::get('entities', 'entities')->name('entities');
        Route::get('anchors', 'anchors')->name('anchors');
        Route::get('actions', 'actions')->name('actions');
        Route::get('catalog', 'catalog')->name('catalog');
    });
