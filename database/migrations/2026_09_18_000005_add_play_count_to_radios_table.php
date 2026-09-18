<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            $table->unsignedInteger('play_count')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            // SQLite's native ALTER TABLE DROP COLUMN refuses to drop a
            // column that still has an index on it, so the index has to go
            // first. Safe-equivalent ordering on MySQL too.
            $table->dropIndex(['play_count']);
        });

        Schema::table('radios', function (Blueprint $table) {
            $table->dropColumn('play_count');
        });
    }
};
