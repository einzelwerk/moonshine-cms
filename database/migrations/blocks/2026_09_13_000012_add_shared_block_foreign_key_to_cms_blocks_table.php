<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column was created nullable and indexed while the library table did not
 * exist yet. Now that `cms_shared_blocks` is there, the reference becomes a
 * real constraint: an insertion pointing at a missing library entry is a data
 * error, not a silent empty block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_blocks', function (Blueprint $table): void {
            $table->foreign('cms_shared_block_id')->references('id')->on('cms_shared_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('cms_blocks', function (Blueprint $table): void {
            $table->dropForeign(['cms_shared_block_id']);
        });
    }
};
