<?php

use App\Support\GenreMapper;

/**
 * These two stations are real, currently-mislabelled rows from the live
 * catalogue (see docs/browsing-at-scale design spec). Their tag strings
 * below are the actual Radio Browser tags for each, fetched directly from
 * the API. Both tests run against the real config/radio-genres.php so a
 * future edit to the taxonomy can't silently break the fix.
 */
it('labels "#100 BEST IBIZA DEEP HOUSE" as Deep House, not Lofi & Chill', function () {
    // Under the old "first matched slug" rule this landed on "lofi"
    // because "chillout" happens to appear before "deep house" in the
    // tag string.
    $tags = 'chill,chill house,chillout,chillout+lounge,club dance,club house,'.
        'community radio,dance,deep,deep house,electronic,fm,high quality audio,'.
        'hits,house,music,news,public radio,radio,soulful house';

    $mapper = new GenreMapper;
    $slugs = $mapper->map($tags);

    expect($slugs)->toContain('lofi')->toContain('deep-house')
        ->and($mapper->primary($slugs))->toBe('deep-house');
});

it('labels "# RdMix DJSET 70s 80s 90s" with a decade genre, not a broad one', function () {
    // Under the old rule this landed on "70s" purely because of tag
    // order — house/dance/pop/oldies are all equally plausible "first
    // matches" depending on that order.
    $tags = "1970s,1980s,1990s,70's,70er,70s,70s disco,80's,80er,80s,90's,90er,90s,".
        'anni 70,anni 80,anni 90,classic hip hop,classic hits,classic hits 60s 70a 80s,'.
        'club house,dance,dance music,dance pop,dancefloor,disco,disco funk,discomusic 70,'.
        'discomusic 80,dj,dj mix,dj mixes,dj remix,dj sets,electronic,euro disco,eurodance,'.
        'fm,funky,funky 70,goldies,high quality audio,hip-hop and rap oldies,hits,house,'.
        'italo disco,music,music 70s 80s 90s,musica,musica anni 70,musica anni 80,oldies,'.
        'oldschool,pop,pop music,public radio,radio,rap,studio 54,techno,vintage music,vintage radio';

    $mapper = new GenreMapper;
    $slugs = $mapper->map($tags);
    $primary = $mapper->primary($slugs);

    expect($slugs)->toContain('70s')->toContain('80s')->toContain('90s')
        ->toContain('pop')->toContain('oldies')->toContain('dance')
        ->and($primary)->toBeIn(['70s', '80s', '90s'])
        ->and($primary)->not->toBe('pop')
        ->and($primary)->not->toBe('oldies')
        ->and($primary)->not->toBe('dance');
});

it('maps deep house tag variants onto both deep-house and house in the real config', function (string $tag) {
    $mapper = new GenreMapper;

    expect($mapper->map($tag))->toEqualCanonicalizing(['deep-house', 'house']);
})->with(['deep house', 'deephouse', 'deep-house']);
