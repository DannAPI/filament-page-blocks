<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menus = (string) config('filament-page-blocks.tables.menus', 'menus');
        $items = (string) config('filament-page-blocks.tables.menu_items', 'menu_items');
        $pages = (string) config('filament-page-blocks.tables.pages', 'pages');

        Schema::create($menus, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('handle')->unique();
            $table->timestamps();
        });

        Schema::create($items, function (Blueprint $table) use ($menus, $items, $pages): void {
            $table->id();
            $table->foreignId('menu_id')->constrained($menus)->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained($items)->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained($pages)->nullOnDelete();
            $table->string('label');
            $table->string('link_type')->default('page');
            $table->string('url', 2048)->nullable();
            $table->string('icon')->nullable();
            $table->string('target', 16)->default('_self');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['menu_id', 'parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('filament-page-blocks.tables.menu_items', 'menu_items'));
        Schema::dropIfExists((string) config('filament-page-blocks.tables.menus', 'menus'));
    }
};
