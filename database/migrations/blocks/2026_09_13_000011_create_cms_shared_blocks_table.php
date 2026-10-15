<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_shared_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // A human readable slug like `promo-10-percent`: it reads in the
            // admin address, survives renaming and seeds the anchor of an
            // insertion.
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_shared_blocks');
    }
};
