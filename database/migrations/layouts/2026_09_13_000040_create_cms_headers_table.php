<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_headers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // The code is the editorial handle of this variant of the header;
            // the layout points at the row, not at the code.
            $table->string('code')->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            // Field values described by the schema of the installation — the same
            // field dictionary blocks use, never a second mechanism.
            $table->json('content')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_headers');
    }
};
