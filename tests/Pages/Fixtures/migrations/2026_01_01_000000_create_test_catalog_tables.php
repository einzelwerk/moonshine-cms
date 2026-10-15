<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('test_series', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
        });

        Schema::create('test_concerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_concerts');
        Schema::dropIfExists('test_series');
    }
};
