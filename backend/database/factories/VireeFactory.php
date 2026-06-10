<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Viree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Viree>
 */
class VireeFactory extends Factory
{
    protected $model = Viree::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-2 months', '-1 day');

        return [
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify('+4 hours'),
            'distance_m' => fake()->numberBetween(300, 8000),
        ];
    }

    /** Virée encore en cours (non clôturée). */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'started_at' => now()->subHours(2),
            'ended_at' => null,
            'distance_m' => null,
        ]);
    }
}
