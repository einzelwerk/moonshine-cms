<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Http\Controllers\ChromeController;
use Ewk\Cms\Layouts\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

/*
 * The endpoints of the chrome. They belong to this package and are registered
 * by its own provider; the prefix and the middleware come from
 * `cms-layouts.routes`.
 */

Route::get('chrome', ChromeController::class)->name('cms.chrome');
Route::get('menus/{code}', MenuController::class)->name('cms.menus.show');
