<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('filament-page-blocks.tables.pages', 'pages'), function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('template')->default('default');
            $table->boolean('is_homepage')->default(false)->index();
            $table->unsignedTinyInteger('homepage_guard')
                ->storedAs('CASE WHEN is_homepage = 1 THEN 1 ELSE NULL END')
                ->unique();
            $table->unsignedInteger('sort')->default(0)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('filament-page-blocks.tables.pages', 'pages'));
    }
};
