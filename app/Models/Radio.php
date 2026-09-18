<?php

namespace App\Models;

use Database\Factories\RadioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Radio extends Model
{
    /** @use HasFactory<RadioFactory> */
    use HasFactory;

    protected $fillable = [
        'station_uuid', 'title', 'artist', 'genre', 'image', 'url',
        'country', 'country_code', 'language', 'homepage', 'codec', 'bitrate',
        'votes', 'click_count', 'source', 'is_favourite', 'is_alive', 'checked_at',
        'local_ok', 'local_checked_at', 'listeners', 'listener_peak',
        'probed_at', 'probe_ok',
    ];

    protected function casts(): array
    {
        return [
            'is_favourite' => 'boolean',
            'is_alive' => 'boolean',
            'local_ok' => 'boolean',
            'probe_ok' => 'boolean',
            'checked_at' => 'datetime',
            'local_checked_at' => 'datetime',
            'probed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }
}
