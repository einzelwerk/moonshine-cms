<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('cms_menu_id');
            // Two levels at most, enforced on save by the model: a third level
            // that silently arrives empty in the response is worse than a
            // refusal.
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // The caption lives on the item, not inside the link: in a menu it
            // is usually shorter than the title of the target.
            $table->json('label');
            // The typed link as `Link::toArray()`; a ready address is never
            // stored. An item without a link is a group caption in the footer.
            $table->json('link')->nullable();
            $table->string('icon')->nullable();
            $table->string('variant')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('cms_menu_id')->references('id')->on('cms_menus')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('cms_menu_items')->cascadeOnDelete();

            $table->index(['cms_menu_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
    }
};
