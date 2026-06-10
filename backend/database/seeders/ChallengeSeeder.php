<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    /**
     * Les 3 défis du mois. Dates roulantes (mois courant) : la démo a
     * toujours des défis vivants, quel que soit le jour de la soutenance.
     */
    public function run(): void
    {
        $challenges = [
            [
                'id' => 'explorateur-du-mois',
                'label' => 'Explorateur du mois',
                'description' => 'Explore 5 nouveaux lieux ce mois-ci',
                'icon' => '◈',
                'criteria' => ['type' => 'nouveaux_lieux', 'min' => 5],
            ],
            [
                'id' => 'marathonien',
                'label' => 'Marathonien',
                'description' => 'Boucle 3 virées dans le mois',
                'icon' => '◎',
                'criteria' => ['type' => 'virees_count', 'min' => 3],
            ],
            [
                'id' => 'semelles-de-feu',
                'label' => 'Semelles de feu',
                'description' => 'Parcours 10 km de nuit ce mois-ci',
                'icon' => '⚡',
                'criteria' => ['type' => 'km', 'min' => 10],
            ],
        ];

        foreach ($challenges as $challenge) {
            Challenge::updateOrCreate(['id' => $challenge['id']], array_merge($challenge, [
                'starts_at' => now()->startOfMonth(),
                'ends_at' => now()->endOfMonth(),
            ]));
        }
    }
}
