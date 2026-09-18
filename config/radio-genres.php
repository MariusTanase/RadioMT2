<?php

return [
    // 'specificity' feeds GenreMapper::primary(): among every genre a
    // station's tags resolve to, the one with the highest specificity wins
    // the display label. Broad catch-all buckets (other, pop, news-talk,
    // world) sit at the bottom so a station tagged [pop, dance, house,
    // deep house] is labelled "Deep House", not "Pop". Ties break on
    // declaration order below, so the result is always deterministic.
    'genres' => [
        'pop' => ['label' => 'Pop', 'sort' => 10, 'specificity' => 1, 'tags' => ['pop', 'pop music', 'musica pop', 'top 40', 'top40', 'chart', 'charts', 'hits', 'adult contemporary', 'contemporary', 'contemporary hits', 'contemporary hits radio', 'hot adult contemporary', 'adult hits', 'top hits', 'top charts', 'top 100', 'top 40 hits', 'popular', 'italian pop', 'greek pop', 'schlager', 'love songs', 'pop en ingles', 'pop-muzyka']],
        'rock' => ['label' => 'Rock', 'sort' => 20, 'specificity' => 2, 'tags' => ['rock', 'rock music', 'musica rock', 'pop rock', 'rock pop', 'rock and roll', 'soft rock', 'progressive rock']],
        'classic-rock' => ['label' => 'Classic Rock', 'sort' => 30, 'specificity' => 6, 'tags' => ['classic rock', 'classicrock', 'rock classics']],
        'alternative' => ['label' => 'Alternative', 'sort' => 40, 'specificity' => 3, 'tags' => ['alternative', 'alternative rock', 'indie', 'indie rock', 'grunge', 'new wave']],
        'metal' => ['label' => 'Metal', 'sort' => 50, 'specificity' => 4, 'tags' => ['metal', 'heavy metal', 'hard rock', 'punk']],
        'dance' => ['label' => 'Dance', 'sort' => 60, 'specificity' => 2, 'tags' => ['dance', 'dance music', 'edm', 'club', 'disco', 'eurodance', 'electronic dance music', 'party', 'pop dance', 'club dance', 'dance pop']],
        'electronic' => ['label' => 'Electronic', 'sort' => 70, 'specificity' => 3, 'tags' => ['electronic', 'electronica', 'techno', 'trance', 'drum and bass', 'dubstep', 'electro', 'downtempo']],
        'house' => ['label' => 'House', 'sort' => 80, 'specificity' => 4, 'tags' => ['house', 'deep house', 'deephouse', 'deep-house', 'tech house', 'progressive house', 'club house', 'soulful house']],
        'deep-house' => ['label' => 'Deep House', 'sort' => 85, 'specificity' => 9, 'tags' => ['deep house', 'deephouse', 'deep-house']],
        'jazz' => ['label' => 'Jazz', 'sort' => 90, 'specificity' => 3, 'tags' => ['jazz', 'smooth jazz', 'jazz music']],
        'blues' => ['label' => 'Blues', 'sort' => 100, 'specificity' => 3, 'tags' => ['blues', 'rhythm and blues']],
        'classical' => ['label' => 'Classical', 'sort' => 110, 'specificity' => 3, 'tags' => ['classical', 'classical music', 'musica clasica', 'opera', 'baroque']],
        'country' => ['label' => 'Country', 'sort' => 120, 'specificity' => 3, 'tags' => ['country', 'country music', 'bluegrass']],
        'folk' => ['label' => 'Folk', 'sort' => 130, 'specificity' => 3, 'tags' => ['folk', 'folk music', 'acoustic']],
        'latin' => ['label' => 'Latin', 'sort' => 140, 'specificity' => 2, 'tags' => [
            'latin', 'latino', 'latin music', 'musica en espanol', 'regional mexican', 'regional mexicana',
            'musica popular mexicana', 'salsa', 'bachata', 'cumbia', 'merengue', 'reggaeton', 'ranchera',
            'banda', 'grupera', 'musica regional', 'mexican music', 'musica mexicana', 'musica tradicional mexicana',
            'traditional mexican music', 'musica regional mexicana', 'musica del recuerdo', 'musica latina',
            'musica latinoamericana', 'tropical', 'musica tropical', 'tropical music', 'norteno', 'banda nortena',
            'nortena', 'grupero', 'vallenato', 'balada', 'balada pop', 'balada en espanol', 'balada romantica',
            'romantica', 'romanticas', 'musica romantica', 'romantic', 'pop clasico', 'pop en espanol',
            'pop en espanol e ingles', 'baladas en espanol', 'baladas', 'spanish contemporary hits', 'latin pop',
            'musica en espanol e ingles',
        ]],
        'reggae' => ['label' => 'Reggae', 'sort' => 150, 'specificity' => 3, 'tags' => ['reggae', 'ska', 'dancehall']],
        'hip-hop' => ['label' => 'Hip Hop', 'sort' => 160, 'specificity' => 4, 'tags' => ['hip hop', 'hiphop', 'hip-hop', 'rap', 'urban', 'classic hip hop']],
        'rnb' => ['label' => 'R&B', 'sort' => 170, 'specificity' => 3, 'tags' => ['r&b', 'rnb', 'rhythm & blues', 'soul', 'funk', 'motown']],
        'oldies' => ['label' => 'Oldies', 'sort' => 180, 'specificity' => 3, 'tags' => ['oldies', 'classic hits', 'golden oldies', 'nostalgia', 'evergreen', 'retro', 'classic', 'classics', 'clasicos', 'clasicos en ingles', 'clasicos en espanol']],
        '60s' => ['label' => '60s', 'sort' => 185, 'specificity' => 6, 'tags' => ['60s', "60's", '1960s', 'sixties', 'anni 60']],
        '70s' => ['label' => '70s', 'sort' => 190, 'specificity' => 6, 'tags' => ['70s', '1970s', 'seventies', "70's", '70er', 'anni 70']],
        '80s' => ['label' => '80s', 'sort' => 200, 'specificity' => 6, 'tags' => ['80s', '1980s', 'eighties', "80's", '80er', 'anni 80']],
        '90s' => ['label' => '90s', 'sort' => 210, 'specificity' => 6, 'tags' => ['90s', '1990s', 'nineties', "90's", '90er', 'anni 90']],
        '2000s' => ['label' => '2000s', 'sort' => 220, 'specificity' => 6, 'tags' => ['2000s', '00s']],
        '2010s' => ['label' => '2010s', 'sort' => 230, 'specificity' => 6, 'tags' => ['2010s', '10s']],
        'lofi' => ['label' => 'Lofi & Chill', 'sort' => 240, 'specificity' => 5, 'tags' => ['lofi', 'lo-fi', 'lo fi', 'chillout', 'chill', 'ambient', 'relax', 'relaxing', 'meditation', 'easy listening', 'lounge', 'smooth', 'ambient and relaxation music']],
        'holiday' => ['label' => 'Holiday', 'sort' => 245, 'specificity' => 6, 'tags' => ['christmas', 'christmas music', 'navidad', 'noel']],
        'kids' => ['label' => 'Kids', 'sort' => 248, 'specificity' => 6, 'tags' => ['kids', 'children', 'infantil']],
        'religious' => ['label' => 'Religious', 'sort' => 250, 'specificity' => 3, 'tags' => ['christian', 'christian music', 'gospel', 'religious', 'catholic', 'worship', 'islamic', 'quran', 'cristiana', 'religion', 'bible']],
        'news-talk' => ['label' => 'News & Talk', 'sort' => 260, 'specificity' => 1, 'tags' => [
            'news', 'talk', 'news talk', 'talk radio', 'local news', 'public radio', 'information', 'politics',
            'current affairs', 'noticias', 'radio hablada', 'hablada', 'noticias locales', 'noticias en espanol',
            'noticias y comentarios', 'noticias nacionales e internacionales', 'news talk music', 'talk & speech',
            'informativa', 'informacion', 'traffic', 'comedy', 'noticias y musica', 'musica y noticias',
            'noticias y deportes',
        ]],
        'sport' => ['label' => 'Sport', 'sort' => 270, 'specificity' => 3, 'tags' => ['sport', 'sports', 'football', 'soccer', 'sports talk', 'deportes', 'sports news', 'noticias y deportes']],
        'world' => ['label' => 'World', 'sort' => 280, 'specificity' => 1, 'tags' => ['world', 'world music', 'international', 'ethnic', 'traditional', 'greek music']],
        'other' => ['label' => 'Other', 'sort' => 999, 'specificity' => 0, 'tags' => []],
    ],

    // High-volume tags that carry no genre meaning. Observed in the live
    // top-300 (https://de1.api.radio-browser.info/json/tags): person and
    // network-operator names, place names, language markers, and generic
    // radio-format words that say nothing about the music itself.
    'ignore' => [
        'music', 'musica', 'radio', 'fm', 'am', 'radio station', 'estacion',
        'entretenimiento', 'moi merino', 'regional', 'local radio',
        'community radio', 'generaliste', 'variety', 'live', 'online',
        'stream', 'internet radio', '24/7', 'mexico', 'norteamerica',
        'america', 'latinoamerica', 'espanol', 'english', 'deutsch',

        // Languages / nationalities / places (no genre signal on their own)
        'greek', 'mex', 'mx', 'usa', 'en todas partes', 'ciudad de mexico',
        'valle de mexico', 'mexico city', 'cdmx', 'veracruz', 'jalisco',
        'chihuahua', 'sinaloa', 'sonora', 'coahuila', 'monterrey',
        'nuevo leon', 'california', 'guadalajara', 'sureste',
        'musica en ingles',

        // Station / network operator names
        'npr', 'college radio', 'university radio', 'mvs radio', 'mvs',
        'exa fm', 'exa', 'ponte exa', 'acir', 'grupo acir', 'radiorama',
        'iheart radio', 'iheart', 'radiopolis', 'grupo radio canon',
        'ntr medios de comunicacion', 'grupo audiorama comunicaciones',
        'mediaset', 'radcap', 'w radio', 'radio caprice',
        'la estacion exacta', 'la estacion naranja',

        // Format / audience descriptors with no genre content
        'juvenil', 'youth', 'culture', 'cultural', 'commercial', 'community',
        'entertainment', 'misc', 'miscellaneous', 'various', 'combo', 'mix',
        'top', 'public', 'publica', 'radio publica', 'non-commercial',
        'public service', 'local', 'local information', 'local music',
        'regional radio', 'regional music', 'full service', 'mainstream',
        'eclectic', 'crossover', 'discography', 'instrumental',
        'experimental', 'social', 'tv', 'online only', 'radio online',
        'programas en vivo', 'musica variada',
    ],
];
