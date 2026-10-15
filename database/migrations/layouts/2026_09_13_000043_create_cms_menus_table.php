<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->json('name');
            // Where the items come from: the core knows the manual source, an
            // application registers its own (categories, for instance). Kept as
            // a plain string for the same reason block types are: the set grows
            // in the application.
            $table->string('source')->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
