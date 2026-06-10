<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // alpha_dash et unique, comme l'inscription l'exige (userName()
            // peut contenir des points, interdits par alpha_dash).
            'username' => str_replace('.', '_', fake()->unique()->userName()),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ];
    }
}
