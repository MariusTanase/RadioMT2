<?php

use App\Support\GenreMapper;

function mapper(): GenreMapper
{
    return new GenreMapper([
        'genres' => [
            'pop' => ['label' => 'Pop', 'sort' => 10, 'tags' => ['pop', 'pop music', 'musica pop']],
            'rock' => ['label' => 'Rock', 'sort' => 20, 'tags' => ['rock', 'pop rock']],
            'latin' => ['label' => 'Latin', 'sort' => 30, 'tags' => ['regional mexicana']],
            'other' => ['label' => 'Other', 'sort' => 999, 'tags' => []],
        ],
        'ignore' => ['music', 'radio', 'moi merino'],
    ]);
}

it('maps a simple tag to its genre', function () {
    expect(mapper()->map('pop'))->toBe(['pop']);
});

it('folds accents so spanish tags match', function () {
    expect(mapper()->map('música pop'))->toBe(['pop']);
});

it('is case and whitespace insensitive', function () {
    expect(mapper()->map('  POP   MUSIC '))->toBe(['pop']);
});

it('maps one tag onto several genres when the taxonomy says so', function () {
    expect(mapper()->map('pop rock'))->toBe(['rock']);
});

it('collects genres from every tag in the list', function () {
    expect(mapper()->map('pop, rock'))->toBe(['pop', 'rock']);
});

it('never returns the same genre twice', function () {
    expect(mapper()->map('pop, pop music, música pop'))->toBe(['pop']);
});

it('drops tags on the ignore list', function () {
    expect(mapper()->map('music, radio, pop'))->toBe(['pop']);
});

it('drops the moi merino tag spam', function () {
    expect(mapper()->map('moi merino'))->toBe(['other']);
});

it('falls back to other when nothing matches', function () {
    expect(mapper()->map('kittens'))->toBe(['other']);
});

it('falls back to other for an empty tag string', function () {
    expect(mapper()->map(''))->toBe(['other'])
        ->and(mapper()->map('  ,  , '))->toBe(['other']);
});

it('matches whole tags only, never substrings', function () {
    // "estacion" must not match because "pop" appears nowhere in it, and a
    // substring matcher would wrongly pair short genre names with longer tags.
    expect(mapper()->map('estacion popular'))->toBe(['other']);
});

it('maps a multiword accented tag', function () {
    expect(mapper()->map('Regional Mexicana'))->toBe(['latin']);
});
