<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('filament-page-blocks.tables.menus', 'menus');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'suppressed_admin_targets')) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->json('suppressed_admin_targets')->nullable()->after('handle');
        });
    }

    public function down(): void
    {
        $table = (string) config('filament-page-blocks.tables.menus', 'menus');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'suppressed_admin_targets')) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->dropColumn('suppressed_admin_targets');
        });
    }
};
