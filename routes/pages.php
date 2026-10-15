<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Http\Controllers\ResolveController;
use Ewk\Cms\Pages\Http\Controllers\ShowBlockController;
use Illuminate\Support\Facades\Route;

/*
 * The HTTP layer of the package: `cms-pages` owns the resolver, so the routes
 * are registered by its own provider and not copied into every installation.
 * The prefix and the middleware come from `cms-pages.routes`, because an
 * application decides both.
 */

$throttle = config('cms-pages.routes.block_throttle', '60,1');

Route::get('resolve', ResolveController::class)->name('cms.resolve');

// The endpoint of one block is the cheap one to hammer: it answers a list that
// grows on the client, so the rate limit lives here and not on the resolver.
Route::get('blocks/{uuid}', ShowBlockController::class)
    ->middleware('throttle:'.(is_string($throttle) ? $throttle : '60,1'))
    ->name('cms.blocks.show');
