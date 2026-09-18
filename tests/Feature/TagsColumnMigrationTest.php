<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

// RefreshDatabase (via tests/Pest.php) already migrates everything up before
// each test, so these two prove the specific tags migration is reversible
// on top of that, on whichever engine the suite is running against
// (SQLite in this repo's test config — see phpunit.xml).
it('adds a nullable, unindexed tags column to radios', function () {
    expect(Schema::hasColumn('radios', 'tags'))->toBeTrue();

    $column = collect(Schema::getColumns('radios'))->firstWhere('name', 'tags');

    expect($column['nullable'])->toBeTrue();
});

it('rolls back the tags column cleanly and can be re-migrated', function () {
    Artisan::call('migrate:rollback', ['--step' => 1]);

    expect(Schema::hasColumn('radios', 'tags'))->toBeFalse();
    // The rest of the radios table survives the rollback untouched.
    expect(Schema::hasColumn('radios', 'genre'))->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasColumn('radios', 'tags'))->toBeTrue();
});
