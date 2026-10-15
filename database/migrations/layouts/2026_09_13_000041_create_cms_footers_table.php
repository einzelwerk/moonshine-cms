<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_footers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            // Logo, copyright, payment icons, brand line: declared fields of
            // the installation. The structure of the footer stays frontend
            // code, only its content is edited here.
            $table->json('content')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_footers');
    }
};
