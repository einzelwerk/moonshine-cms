<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('blockable');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('slot')->nullable();
            $table->string('type')->index();
            $table->string('variant')->nullable();
            $table->string('name');
            $table->string('anchor')->nullable();
            $table->json('content')->nullable();
            // No foreign key yet: `cms_shared_blocks` arrives with the shared
            // block library in a later release.
            $table->unsignedBigInteger('cms_shared_block_id')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('cms_blocks');

            $table->index(['blockable_type', 'blockable_id', 'sort_order']);
            $table->index(['parent_id', 'sort_order']);

            // An anchor addresses a block within its owner, both as a link
            // target and as the query-parameter namespace of the block.
            $table->unique(['blockable_type', 'blockable_id', 'anchor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_blocks');
    }
};
