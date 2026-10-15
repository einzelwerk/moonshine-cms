<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Http\Controllers\SitemapFileController;
use Ewk\Cms\Seo\Http\Controllers\SitemapIndexController;
use Illuminate\Support\Facades\Route;

/*
 * The sitemap feed. The prefix and the middleware come from
 * `cms-seo.sitemap.routes`, because an application decides both.
 */

Route::get('sitemap', SitemapIndexController::class)->name('cms.sitemap.index');

Route::get('sitemap/{id}', SitemapFileController::class)
    ->where('id', '[A-Za-z0-9_-]+')
    ->name('cms.sitemap.show');
