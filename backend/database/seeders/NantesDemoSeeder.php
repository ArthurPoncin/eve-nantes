<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NantesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $salle = Venue::query()->create([
            'name' => 'Stereolux',
            'slug' => 'stereolux',
            'address_line' => '4 Boulevard Leon Bureau',
            'postal_code' => '44200',
            'city' => 'Nantes',
            'capacity' => 1200,
            'mood' => 'decouverte',
        ]);

        $orga = Organizer::query()->create([
            'name' => 'Nantes Events',
            'email' => 'contact@nantes-events.local',
            'website' => 'https://nantes-events.local',
        ]);

        $music = Category::query()->create([
            'name' => 'Musique',
            'slug' => 'musique',
        ]);

        $event = Event::query()->create([
            'title' => 'Concert electro a Nantes',
            'slug' => Str::slug('Concert electro a Nantes'),
            'description' => 'Soiree electro avec artistes locaux.',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'price_cents' => 1800,
            'max_capacity' => 800,
            'is_published' => true,
            'venue_id' => $salle->id,
            'organizer_id' => $orga->id,
        ]);

        $event->categories()->attach($music->id);

        // Lieux variés pour alimenter le filtre par ambiance sur la landing.
        foreach (Venue::MOODS as $mood) {
            Venue::factory()->count(3)->create(['mood' => $mood]);
        }
    }
}
