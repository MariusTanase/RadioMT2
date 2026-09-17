<?php

namespace Database\Factories;

use App\Models\Radio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Radio> */
class RadioFactory extends Factory
{
    public function definition(): array
    {
        $genre = fake()->randomElement(['Pop', 'Dance', 'Lofi', 'Deep House', 'J-Pop']);

        return [
            'title' => fake()->unique()->words(2, true),
            'artist' => $genre,
            'genre' => $genre,
            'image' => 'https://example.test/'.fake()->uuid().'.png',
            'url' => 'https://example.test/stream/'.fake()->uuid(),
        ];
    }
}
