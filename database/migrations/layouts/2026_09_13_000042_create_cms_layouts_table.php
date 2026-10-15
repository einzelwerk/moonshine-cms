<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_layouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // The contract with the frontend: every code has a shell component
            // there. A page references a layout by this string and never by id,
            // which is why no foreign key points here from `cms_pages`.
            $table->string('code')->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            // The second and last step of layout selection: the page names a
            // layout, otherwise this one is used.
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('cms_header_id')->nullable();
            $table->unsignedBigInteger('cms_footer_id')->nullable();
            // Container width and a service class of the shell — the only two
            // presentation knobs a layout has; it never renders content.
            $table->string('container')->nullable();
            $table->string('body_class')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('cms_header_id')->references('id')->on('cms_headers')->nullOnDelete();
            $table->foreign('cms_footer_id')->references('id')->on('cms_footers')->nullOnDelete();

            $table->index(['is_active', 'sort_order']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_layouts');
    }
};
