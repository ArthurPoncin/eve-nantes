<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StatsService
{
    /** Fenêtre du classement « Pilier de bar », en jours. */
    public const PILIER_WINDOW_DAYS = 90;

    /** Minimum de check-ins pour prétendre au trône. */
    public const PILIER_MIN = 2;

    /**
     * Le « Wrapped » nocturne de l'utilisateur : agrégats calculés à la volée
     * (échelle démo — pas de table matérialisée).
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $virees = $user->virees()->whereNotNull('ended_at');

        // Répartition des check-ins par ambiance, la dominante en tête.
        $moods = $user->checkins()
            ->join('venues', 'venues.id', '=', 'checkins.venue_id')
            ->whereNotNull('venues.mood')
            ->selectRaw('venues.mood, count(*) as count')
            ->groupBy('venues.mood')
            ->orderByDesc('count')
            ->orderBy('venues.mood')
            ->get()
            ->map(fn ($row): array => ['mood' => $row->mood, 'count' => (int) $row->count]);

        // Check-ins par lieu : nourrit le lieu fétiche et la heatmap perso.
        $byVenue = $user->checkins()
            ->join('venues', 'venues.id', '=', 'checkins.venue_id')
            ->selectRaw(
                'venues.slug, venues.name, venues.latitude, venues.longitude, count(*) as checkins_count',
            )
            ->groupBy('venues.id', 'venues.slug', 'venues.name', 'venues.latitude', 'venues.longitude')
            ->orderByDesc('checkins_count')
            ->orderBy('venues.name')
            ->get();

        $favorite = $byVenue->first();

        return [
            'virees_count' => (clone $virees)->count(),
            'checkins_count' => $user->checkins()->count(),
            'distinct_venues' => $user->checkins()->distinct()->count('venue_id'),
            'total_km' => round((clone $virees)->sum('distance_m') / 1000, 1),
            'streak_weeks' => $this->streakWeeks($user),
            'dominant_mood' => $moods->first()['mood'] ?? null,
            'moods' => $moods->values()->all(),
            'favorite_venue' => $favorite ? [
                'slug' => $favorite->slug,
                'name' => $favorite->name,
                'checkins_count' => (int) $favorite->checkins_count,
            ] : null,
            'heatmap' => $byVenue
                ->filter(fn ($row): bool => $row->latitude !== null && $row->longitude !== null)
                ->map(fn ($row): array => [
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'latitude' => (float) $row->latitude,
                    'longitude' => (float) $row->longitude,
                    'checkins_count' => (int) $row->checkins_count,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Le « Pilier » du lieu : l'utilisateur au plus de check-ins sur 90 jours.
     * Égalité départagée par le premier check-in le plus ancien (« le fidèle
     * le plus ancien garde la couronne »). Cache court : classement public.
     *
     * @return array{username: string, checkins_count: int, first_checkin_at: string}|null
     */
    public function pilierFor(Venue $venue): ?array
    {
        return Cache::remember(
            "cache:pilier:{$venue->id}",
            now()->addSeconds(60),
            function () use ($venue): ?array {
                $top = $venue->checkins()
                    ->where('happened_at', '>=', now()->subDays(self::PILIER_WINDOW_DAYS))
                    ->selectRaw('user_id, count(*) as checkins_count, min(happened_at) as first_at')
                    ->groupBy('user_id')
                    // havingRaw : PostgreSQL n'accepte pas un alias de SELECT
                    // dans le HAVING (sqlite si).
                    ->havingRaw('count(*) >= ?', [self::PILIER_MIN])
                    ->orderByDesc('checkins_count')
                    ->orderBy('first_at')
                    ->first();

                if ($top === null) {
                    return null;
                }

                return [
                    'username' => User::findOrFail($top->user_id)->username,
                    'checkins_count' => (int) $top->checkins_count,
                    'first_checkin_at' => Carbon::parse($top->first_at)->toIso8601String(),
                ];
            },
        );
    }

    /**
     * Semaines consécutives (ISO) avec au moins une virée bouclée. La semaine
     * courante encore vide ne casse pas la série : elle n'est pas finie.
     * Calculé en PHP pour rester portable SQLite/PostgreSQL.
     */
    private function streakWeeks(User $user): int
    {
        $weeks = $user->virees()
            ->whereNotNull('ended_at')
            ->pluck('started_at')
            ->map(fn ($date): string => Carbon::parse($date)->format('o-W'))
            ->unique()
            ->flip();

        $cursor = now();
        if (! $weeks->has($cursor->format('o-W'))) {
            $cursor = $cursor->subWeek();
        }

        $streak = 0;
        while ($weeks->has($cursor->format('o-W'))) {
            $streak++;
            $cursor = $cursor->subWeek();
        }

        return $streak;
    }
}
