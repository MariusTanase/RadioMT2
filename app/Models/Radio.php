<?php

namespace App\Models;

use Database\Factories\RadioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Radio extends Model
{
    /** @use HasFactory<RadioFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'genre',
        'image',
        'url',
    ];
}
