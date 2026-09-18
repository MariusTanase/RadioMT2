<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

// RefreshDatabase (via tests/Pest.php) already migrates everything up before
// each test. These prove the play_count migration is reversible on top of
// that on whichever engine the suite is running against (SQLite in this
// repo's test config — see phpunit.xml) — SQLite refuses to drop an indexed
// column directly, so the migration's down() must drop the index first.
it('adds an indexed, unsigned play_count column defaulting to zero', function () {
    expect(Schema::hasColumn('radios', 'play_count'))->toBeTrue();

    $indexes = collect(Schema::getIndexes('radios'));

    expect($indexes->contains(fn ($index) => $index['columns'] === ['play_count']))->toBeTrue();
});

it('rolls back the play_count column cleanly and can be re-migrated', function () {
    Artisan::call('migrate:rollback', ['--step' => 1]);

    expect(Schema::hasColumn('radios', 'play_count'))->toBeFalse();
    // The rest of the radios table survives the rollback untouched.
    expect(Schema::hasColumn('radios', 'click_count'))->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasColumn('radios', 'play_count'))->toBeTrue();
});
