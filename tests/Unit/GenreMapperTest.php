<?php

use App\Support\GenreMapper;

function mapper(): GenreMapper
{
    return new GenreMapper([
        'genres' => [
            'pop' => ['label' => 'Pop', 'sort' => 10, 'tags' => ['pop', 'pop music', 'musica pop', 'pop rock']],
            'rock' => ['label' => 'Rock', 'sort' => 20, 'tags' => ['rock', 'pop rock']],
            'latin' => ['label' => 'Latin', 'sort' => 30, 'tags' => ['regional mexicana']],
            'other' => ['label' => 'Other', 'sort' => 999, 'tags' => []],
        ],
        'ignore' => ['music', 'radio', 'moi merino', 'Estación'],
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
    expect(mapper()->map('pop rock'))->toBe(['pop', 'rock']);
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

it('normalises the ignore list, not just the incoming tags', function () {
    // 'Estación' is registered un-normalised; an incoming 'estacion' must still be ignored.
    expect(mapper()->map('estacion'))->toBe(['other'])
        ->and(mapper()->map('ESTACIÓN, pop'))->toBe(['pop']);
});

// --- deep house -----------------------------------------------------------

function deepHouseMapper(): GenreMapper
{
    return new GenreMapper([
        'genres' => [
            'house' => ['label' => 'House', 'sort' => 80, 'specificity' => 4, 'tags' => ['house', 'deep house', 'deephouse', 'deep-house', 'tech house']],
            'deep-house' => ['label' => 'Deep House', 'sort' => 85, 'specificity' => 9, 'tags' => ['deep house', 'deephouse', 'deep-house']],
            'lofi' => ['label' => 'Lofi & Chill', 'sort' => 240, 'specificity' => 5, 'tags' => ['chillout', 'chill']],
            'other' => ['label' => 'Other', 'sort' => 999, 'specificity' => 0, 'tags' => []],
        ],
        'ignore' => [],
    ]);
}

it('maps every deep house tag variant onto both deep-house and house', function (string $tag) {
    expect(deepHouseMapper()->map($tag))->toEqualCanonicalizing(['deep-house', 'house']);
})->with(['deep house', 'deephouse', 'deep-house']);

it('keeps plain house out of deep-house', function () {
    expect(deepHouseMapper()->map('house'))->toBe(['house']);
});

// --- primary() --------------------------------------------------------------

it('picks the most specific genre as primary, not the first matched', function () {
    $mapper = deepHouseMapper();

    expect($mapper->primary(['house', 'deep-house']))->toBe('deep-house')
        ->and($mapper->primary(['deep-house', 'house']))->toBe('deep-house');
});

it('breaks specificity ties on declaration order', function () {
    $mapper = new GenreMapper([
        'genres' => [
            'a' => ['label' => 'A', 'sort' => 1, 'specificity' => 5, 'tags' => []],
            'b' => ['label' => 'B', 'sort' => 2, 'specificity' => 5, 'tags' => []],
            'other' => ['label' => 'Other', 'sort' => 999, 'specificity' => 0, 'tags' => []],
        ],
        'ignore' => [],
    ]);

    expect($mapper->primary(['b', 'a']))->toBe('a');
});

it('falls back to other when given an empty slug list', function () {
    expect(mapper()->primary([]))->toBe('other');
});
