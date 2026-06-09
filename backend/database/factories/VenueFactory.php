<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'address_line' => fake()->streetAddress(),
            'postal_code' => '44'.fake()->numberBetween(100, 999),
            'city' => 'Nantes',
            'mood' => Arr::random(Venue::MOODS),
            'capacity' => fake()->numberBetween(80, 2000),
            'latitude' => fake()->latitude(47.18, 47.25),
            'longitude' => fake()->longitude(-1.62, -1.50),
        ];
    }
}
