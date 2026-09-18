<?php

return [
    'genres' => [
        'pop' => ['label' => 'Pop', 'sort' => 10, 'tags' => ['pop', 'pop music', 'musica pop', 'top 40', 'top40', 'chart', 'charts', 'hits', 'adult contemporary', 'contemporary']],
        'rock' => ['label' => 'Rock', 'sort' => 20, 'tags' => ['rock', 'rock music', 'musica rock', 'pop rock', 'rock pop', 'rock and roll']],
        'classic-rock' => ['label' => 'Classic Rock', 'sort' => 30, 'tags' => ['classic rock', 'classicrock', 'rock classics']],
        'alternative' => ['label' => 'Alternative', 'sort' => 40, 'tags' => ['alternative', 'alternative rock', 'indie', 'indie rock', 'grunge']],
        'metal' => ['label' => 'Metal', 'sort' => 50, 'tags' => ['metal', 'heavy metal', 'hard rock', 'punk']],
        'dance' => ['label' => 'Dance', 'sort' => 60, 'tags' => ['dance', 'dance music', 'edm', 'club', 'disco']],
        'electronic' => ['label' => 'Electronic', 'sort' => 70, 'tags' => ['electronic', 'electronica', 'techno', 'trance', 'drum and bass', 'dubstep']],
        'house' => ['label' => 'House', 'sort' => 80, 'tags' => ['house', 'deep house', 'tech house', 'progressive house']],
        'jazz' => ['label' => 'Jazz', 'sort' => 90, 'tags' => ['jazz', 'smooth jazz', 'jazz music']],
        'blues' => ['label' => 'Blues', 'sort' => 100, 'tags' => ['blues', 'rhythm and blues']],
        'classical' => ['label' => 'Classical', 'sort' => 110, 'tags' => ['classical', 'classical music', 'musica clasica', 'opera', 'baroque']],
        'country' => ['label' => 'Country', 'sort' => 120, 'tags' => ['country', 'country music', 'bluegrass']],
        'folk' => ['label' => 'Folk', 'sort' => 130, 'tags' => ['folk', 'folk music', 'acoustic']],
        'latin' => ['label' => 'Latin', 'sort' => 140, 'tags' => ['latin', 'latino', 'latin music', 'musica en espanol', 'regional mexican', 'regional mexicana', 'musica popular mexicana', 'salsa', 'bachata', 'cumbia', 'merengue', 'reggaeton', 'ranchera', 'banda', 'grupera']],
        'reggae' => ['label' => 'Reggae', 'sort' => 150, 'tags' => ['reggae', 'ska', 'dancehall']],
        'hip-hop' => ['label' => 'Hip Hop', 'sort' => 160, 'tags' => ['hip hop', 'hiphop', 'hip-hop', 'rap', 'urban']],
        'rnb' => ['label' => 'R&B', 'sort' => 170, 'tags' => ['r&b', 'rnb', 'rhythm & blues', 'soul', 'funk', 'motown']],
        'oldies' => ['label' => 'Oldies', 'sort' => 180, 'tags' => ['oldies', 'classic hits', 'golden oldies', 'nostalgia', 'evergreen']],
        '80s' => ['label' => '80s', 'sort' => 190, 'tags' => ['80s', '1980s', 'eighties']],
        '90s' => ['label' => '90s', 'sort' => 200, 'tags' => ['90s', '1990s', 'nineties']],
        '70s' => ['label' => '70s', 'sort' => 210, 'tags' => ['70s', '1970s', 'seventies']],
        'lofi' => ['label' => 'Lofi & Chill', 'sort' => 220, 'tags' => ['lofi', 'lo-fi', 'lo fi', 'chillout', 'chill', 'ambient', 'relax', 'relaxing', 'meditation']],
        'religious' => ['label' => 'Religious', 'sort' => 230, 'tags' => ['christian', 'christian music', 'gospel', 'religious', 'catholic', 'worship', 'islamic', 'quran']],
        'news-talk' => ['label' => 'News & Talk', 'sort' => 240, 'tags' => ['news', 'talk', 'news talk', 'talk radio', 'local news', 'public radio', 'information', 'politics', 'current affairs']],
        'sport' => ['label' => 'Sport', 'sort' => 250, 'tags' => ['sport', 'sports', 'football', 'soccer', 'sports talk']],
        'world' => ['label' => 'World', 'sort' => 260, 'tags' => ['world', 'world music', 'international', 'ethnic', 'traditional']],
        'other' => ['label' => 'Other', 'sort' => 999, 'tags' => []],
    ],

    // High-volume tags that carry no genre meaning. Observed in the live top-45:
    // "moi merino" is a person's name attached to 1,783 stations.
    'ignore' => [
        'music', 'musica', 'radio', 'fm', 'am', 'radio station', 'estacion',
        'entretenimiento', 'moi merino', 'regional', 'local radio',
        'community radio', 'generaliste', 'variety', 'live', 'online',
        'stream', 'internet radio', '24/7', 'mexico', 'norteamerica',
        'america', 'latinoamerica', 'espanol', 'english', 'deutsch',
    ],
];
