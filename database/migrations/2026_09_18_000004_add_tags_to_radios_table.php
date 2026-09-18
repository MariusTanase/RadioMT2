<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            // Raw Radio Browser comma-separated tag string. Deliberately not
            // indexed: SQLite's native ALTER TABLE DROP COLUMN refuses to
            // drop a column that still has an index on it, and this column
            // never needs to be searched directly (genre_radio and the
            // genre/artist labels are what queries actually filter on).
            $table->text('tags')->nullable()->after('genre');
        });
    }

    public function down(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
