<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_slot_assignments', function (Blueprint $table): void {
            $table->id();
            // The owner is a header or a footer row: a slot is a place in their
            // markup, not in the layout. No uuid here — the row is a link
            // between two entities that have one, and nothing outside addresses
            // it.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            // A code of the closed slot dictionary, extended by the
            // installation through `cms-layouts.menu_slots`.
            $table->string('slot');
            $table->unsignedBigInteger('cms_menu_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('cms_menu_id')->references('id')->on('cms_menus')->cascadeOnDelete();

            $table->index(['owner_type', 'owner_id', 'slot', 'sort_order'], 'cms_menu_slot_assignments_owner_slot_index');
            $table->index('cms_menu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_slot_assignments');
    }
};
