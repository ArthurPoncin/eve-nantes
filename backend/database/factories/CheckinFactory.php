<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viree_id' => Viree::factory(),
            'user_id' => User::factory(),
            'venue_id' => Venue::factory(),
            'happened_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
