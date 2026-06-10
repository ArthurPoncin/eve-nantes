<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Les 8 badges NOCTAMBULE. Les critères collent au schéma réel :
     * soirées partagées (soirees), avis postés (reviews), check-ins et
     * virées bouclées (checkins, virees).
     */
    public function run(): void
    {
        $badges = [
            [
                'id' => 'noctambule',
                'label' => 'Noctambule',
                'description' => '5 soirées composées et partagées',
                'icon' => '◉',
                'criteria' => ['type' => 'soirees_count', 'min' => 5],
            ],
            [
                'id' => 'explorateur',
                'label' => 'Explorateur',
                'description' => '3 lieux différents écumés',
                'icon' => '◇',
                'criteria' => ['type' => 'distinct_venues', 'min' => 3],
            ],
            [
                'id' => 'melomane',
                'label' => 'Mélomane',
                'description' => '3 ambiances différentes vécues',
                'icon' => '♪',
                'criteria' => ['type' => 'distinct_moods', 'min' => 3],
            ],
            [
                'id' => 'fidele',
                'label' => 'Fidèle',
                'description' => '3 soirées au même lieu',
                'icon' => '✦',
                'criteria' => ['type' => 'same_venue', 'min' => 3],
            ],
            [
                'id' => 'critique',
                'label' => 'Critique',
                'description' => 'Premier avis posté sur un lieu',
                'icon' => '☆',
                'criteria' => ['type' => 'reviews_count', 'min' => 1],
            ],
            [
                'id' => 'habitue',
                'label' => 'Habitué',
                'description' => '10 check-ins « J\'y suis »',
                'icon' => '⚑',
                'criteria' => ['type' => 'checkins_count', 'min' => 10],
            ],
            [
                'id' => 'arpenteur',
                'label' => 'Arpenteur',
                'description' => '5 virées bouclées',
                'icon' => '➤',
                'criteria' => ['type' => 'virees_count', 'min' => 5],
            ],
            [
                'id' => 'grand-marcheur',
                'label' => 'Grand Marcheur',
                'description' => '15 km arpentés de nuit',
                'icon' => '∿',
                'criteria' => ['type' => 'night_km', 'min' => 15],
            ],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['id' => $badge['id']], $badge);
        }
    }
}
