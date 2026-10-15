<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_metadata', function (Blueprint $table): void {
            $table->id();

            // One row per owner: metadata belongs to a page, a tour, an event,
            // a venue, a city or an artist, and the pair carries the unique
            // index rather than a check at the application level.
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // Translatable values are language dictionaries, exactly as in
            // blocks. `keywords` is not carried over.
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->json('og_title')->nullable();
            $table->json('og_description')->nullable();
            $table->string('og_type')->nullable();

            // The picture is a reference to the media library, not a path. No
            // foreign key: the CMS keeps none into the tables of the library.
            $table->unsignedBigInteger('og_image_id')->nullable();

            $table->string('tw_card')->nullable();
            $table->json('tw_title')->nullable();
            $table->json('tw_description')->nullable();
            $table->unsignedBigInteger('tw_image_id')->nullable();

            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);

            // Manual override of the canonical address, a field for exceptions.
            // Long enough for an address with a query string: the default 255
            // is short for one.
            $table->string('canonical', 2048)->nullable();

            $table->timestamps();

            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_metadata');
    }
};
