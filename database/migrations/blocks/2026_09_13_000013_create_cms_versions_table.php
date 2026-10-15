<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table is created in its target shape. In this version one row per owner
 * is kept: publishing overwrites it and raises the number. Moving to a history
 * is dropping the overwrite plus a list screen, the schema does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_versions', function (Blueprint $table): void {
            $table->id();
            // Spelled out instead of `morphs()`: the unique index below
            // already serves every lookup of an owner's snapshot, and a
            // second index over the same pair would only cost writes.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedInteger('number')->default(1);
            // Settings of the block tree plus the owner attributes, never a
            // rendered response: rendering happens on request.
            $table->json('snapshot');
            // Published languages: filled with the whole catalog, so choosing
            // languages at publication arrives without a migration.
            $table->json('locales');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_versions');
    }
};
