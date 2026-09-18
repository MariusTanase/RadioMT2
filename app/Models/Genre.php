<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    /** @return BelongsToMany<Radio, $this> */
    public function radios(): BelongsToMany
    {
        return $this->belongsToMany(Radio::class);
    }
}
