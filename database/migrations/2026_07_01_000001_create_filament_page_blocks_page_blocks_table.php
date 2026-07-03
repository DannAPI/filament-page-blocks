<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pages = (string) config('filament-page-blocks.tables.pages', 'pages');

        Schema::create((string) config('filament-page-blocks.tables.page_blocks', 'page_blocks'), function (Blueprint $table) use ($pages): void {
            $table->id();
            $table->foreignId('page_id')->constrained($pages)->cascadeOnDelete();
            $table->uuid('key');
            $table->string('type')->index();
            $table->json('data');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
            $table->unique(['page_id', 'key']);
            $table->index(['page_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('filament-page-blocks.tables.page_blocks', 'page_blocks'));
    }
};
