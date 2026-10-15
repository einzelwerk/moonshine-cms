<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Support\PageLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type')->index();
            // Localized admin caption; the slug itself is not translatable in
            // the first version.
            $table->json('name');
            $table->string('slug', PageLimits::SLUG_LENGTH);
            // Parent and position are the truth, the path is derived. The
            // unique index is the real guarantee against colliding addresses;
            // 512 utf8mb4 characters are 2048 bytes, well inside the 3072 byte
            // InnoDB key limit.
            $table->string('path', PageLimits::PATH_LENGTH)->unique();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            // No foreign key: the pages do not depend on the layouts, and a
            // page may name a layout the installation has not created yet.
            $table->string('layout_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('cms_pages');

            $table->index(['parent_id', 'sort_order']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
