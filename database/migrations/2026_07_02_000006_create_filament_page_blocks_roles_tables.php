<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roles = (string) config('filament-page-blocks.tables.roles', 'roles');
        $roleUser = (string) config('filament-page-blocks.tables.role_user', 'role_user');
        $users = (string) config('filament-page-blocks.tables.users', 'users');

        if (Schema::hasTable($users) && ! Schema::hasColumn($users, 'is_system')) {
            Schema::table($users, static function (Blueprint $table): void {
                $table->boolean('is_system')->default(false)->index();
            });
        }

        Schema::create($roles, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });

        Schema::create($roleUser, function (Blueprint $table) use ($roles): void {
            $table->id();
            $table->foreignId('role_id')->constrained($roles)->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('filament-page-blocks.tables.role_user', 'role_user'));
        Schema::dropIfExists((string) config('filament-page-blocks.tables.roles', 'roles'));

        $users = (string) config('filament-page-blocks.tables.users', 'users');
        if (Schema::hasTable($users) && Schema::hasColumn($users, 'is_system')) {
            Schema::table($users, static function (Blueprint $table): void {
                $table->dropColumn('is_system');
            });
        }
    }
};
