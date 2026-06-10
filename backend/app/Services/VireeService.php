<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\User;
use App\Models\Venue;
use App\Models\Viree;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class VireeService
{
    /**
     * Au-delà de cette inactivité, la virée active est considérée comme
     * oubliée : elle est clôturée paresseusement au prochain accès (pas de
     * cron ni de queue worker dans l'infra de démo).
     */
    public const IDLE_HOURS = 6;

    public function __construct(
        private readonly WeatherService $weather,
        private readonly AIService $ai,
    ) {
    }

    /**
     * La virée active de l'utilisateur (check-ins et lieux chargés), après
     * clôture paresseuse d'une éventuelle virée laissée à l'abandon.
     */
    public function current(User $user): ?Viree
    {
        $this->closeStale($user);

        return $user->virees()->active()->with('checkins.venue')->first();
    }

    /**
     * Enregistre un check-in « J'y suis » : rejoint la virée active ou en
     * démarre une nouvelle (démarrage implicite). Un check-in consécutif au
     * même lieu est ignoré (idempotent).
     *
     * @return array{viree: Viree, created: bool}
     */
    public function checkIn(User $user, Venue $venue): array
    {
        $this->closeStale($user);

        $viree = $user->virees()->active()->first()
            ?? $user->virees()->create(['started_at' => now()]);

        $last = $viree->checkins()->latest('happened_at')->latest('id')->first();

        $created = false;
        if ($last === null || $last->venue_id !== $venue->id) {
            $viree->checkins()->create([
                'user_id' => $user->id,
                'venue_id' => $venue->id,
                'happened_at' => now(),
            ]);
            $created = true;
        }

        return ['viree' => $viree->load('checkins.venue'), 'created' => $created];
    }

    /**
     * Clôture la virée : distance parcourue, météo du moment et narration IA.
     * La météo et l'IA sont des enrichissements — leur échec ne doit jamais
     * faire échouer la clôture.
     */
    public function close(Viree $viree): Viree
    {
        $viree->load('checkins.venue');
        $checkins = $viree->checkins;

        try {
            $weather = $this->weather->current();
        } catch (Throwable) {
            $weather = null;
        }

        $venues = $checkins->pluck('venue.name')->all();
        $moods = $checkins->pluck('venue.mood')->filter()->unique()->values()->all();
        $distance = $this->distanceMeters($checkins);
        $endedAt = now();
        $duration = $viree->started_at->diffInMinutes($endedAt);

        $viree->update([
            'ended_at' => $endedAt,
            'distance_m' => $distance,
            'weather_snapshot' => $weather,
            'ai_narrative' => $this->ai->narrateViree([
                'venues' => $venues,
                'moods' => $moods,
                'distance_km' => number_format($distance / 1000, 1, ',', ' '),
                'duration_min' => (int) $duration,
                'weather' => $weather['condition'] ?? '',
            ]),
        ]);

        return $viree;
    }

    /** Clôture silencieuse de la virée active laissée inactive trop longtemps. */
    private function closeStale(User $user): void
    {
        $viree = $user->virees()->active()->first();
        if ($viree === null) {
            return;
        }

        $lastActivity = $viree->checkins()->max('happened_at') ?? $viree->started_at;
        if (now()->diffInHours($lastActivity, true) >= self::IDLE_HOURS) {
            $this->close($viree);
        }
    }

    /**
     * Distance à vol d'oiseau cumulée entre les lieux consécutifs (haversine).
     *
     * @param  Collection<int, Checkin>  $checkins
     */
    private function distanceMeters(Collection $checkins): int
    {
        $total = 0.0;
        $previous = null;

        foreach ($checkins as $checkin) {
            $venue = $checkin->venue;
            if ($venue === null || $venue->latitude === null || $venue->longitude === null) {
                continue;
            }
            if ($previous !== null) {
                $total += $this->haversine(
                    $previous->latitude,
                    $previous->longitude,
                    $venue->latitude,
                    $venue->longitude,
                );
            }
            $previous = $venue;
        }

        return (int) round($total);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
