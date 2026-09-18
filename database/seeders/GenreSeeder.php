<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        foreach (config('radio-genres.genres') as $slug => $genre) {
            $rows[] = [
                'slug' => $slug,
                'name' => $genre['label'],
                'sort_order' => $genre['sort'],
            ];
        }

        Genre::upsert($rows, uniqueBy: ['slug'], update: ['name', 'sort_order']);
    }
}
