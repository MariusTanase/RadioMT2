<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            $table->string('station_uuid', 36)->nullable()->unique()->after('id');
            $table->string('country')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('language')->nullable();
            $table->string('homepage', 2048)->nullable();
            $table->string('codec', 16)->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->unsignedInteger('votes')->default(0);
            $table->unsignedInteger('click_count')->default(0)->index();
            $table->string('source', 16)->default('seed')->index();
            $table->boolean('is_favourite')->default(false)->index();
            $table->boolean('is_alive')->default(true)->index();
            $table->timestamp('checked_at')->nullable();
            $table->boolean('local_ok')->nullable();
            $table->timestamp('local_checked_at')->nullable();
            $table->unsignedInteger('listeners')->nullable()->index();
            $table->unsignedInteger('listener_peak')->nullable();
            $table->timestamp('probed_at')->nullable();
            $table->boolean('probe_ok')->nullable();
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            // SQLite's native ALTER TABLE DROP COLUMN refuses to drop a column
            // that still has an index on it, so every index added by up() must
            // be dropped explicitly first. This is a no-op-equivalent, safe
            // ordering on MySQL too.
            $table->dropUnique(['station_uuid']);
            $table->dropIndex(['country_code']);
            $table->dropIndex(['click_count']);
            $table->dropIndex(['source']);
            $table->dropIndex(['is_favourite']);
            $table->dropIndex(['is_alive']);
            $table->dropIndex(['listeners']);
            $table->dropIndex(['title']);
        });

        Schema::table('radios', function (Blueprint $table) {
            $table->dropColumn([
                'station_uuid', 'country', 'country_code', 'language', 'homepage',
                'codec', 'bitrate', 'votes', 'click_count', 'source', 'is_favourite',
                'is_alive', 'checked_at', 'local_ok', 'local_checked_at',
                'listeners', 'listener_peak', 'probed_at', 'probe_ok',
            ]);
        });
    }
};
