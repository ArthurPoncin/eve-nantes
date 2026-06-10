<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Support\Carbon;

class ChallengeService
{
    /**
     * Les défis actifs, avec la progression de l'utilisateur (recalculée à la
     * volée pour l'affichage ; completed_at vient du pivot).
     *
     * @return list<array{id: string, label: string, description: string, icon: string, goal: int, progress: int, completed: bool, completed_at: string|null, ends_at: string}>
     */
    public function forUser(User $user): array
    {
        $pivots = $user->challenges()->get()->keyBy('id');

        return Challenge::active()->orderBy('id')->get()
            ->map(function (Challenge $challenge) use ($user, $pivots): array {
                $goal = (int) $challenge->criteria['min'];
                $completedAt = $pivots->get($challenge->id)?->pivot->completed_at;

                return [
                    'id' => $challenge->id,
                    'label' => $challenge->label,
                    'description' => $challenge->description,
                    'icon' => $challenge->icon,
                    'goal' => $goal,
                    'progress' => min($this->progress($user, $challenge), $goal),
                    'completed' => $completedAt !== null,
                    'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
                    'ends_at' => $challenge->ends_at->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Recalcule la progression des défis actifs et marque ceux qui viennent
     * d'être bouclés. Appelé après chaque check-in et clôture de virée.
     *
     * @return list<string> ids des défis qui viennent d'être complétés
     */
    public function evaluate(User $user): array
    {
        $newlyCompleted = [];

        foreach (Challenge::active()->get() as $challenge) {
            $existing = $user->challenges()->whereKey($challenge->id)->first();
            if ($existing !== null && $existing->pivot->completed_at !== null) {
                continue; // déjà bouclé : on n'y touche plus
            }

            $progress = $this->progress($user, $challenge);
            $done = $progress >= (int) $challenge->criteria['min'];

            $user->challenges()->syncWithoutDetaching([
                $challenge->id => [
                    'progress' => $progress,
                    'completed_at' => $done ? now() : null,
                ],
            ]);

            if ($done) {
                $newlyCompleted[] = $challenge->id;
            }
        }

        return $newlyCompleted;
    }

    /** Progression de l'utilisateur sur la fenêtre [starts_at, ends_at]. */
    private function progress(User $user, Challenge $challenge): int
    {
        $window = [$challenge->starts_at, $challenge->ends_at];

        return match ($challenge->criteria['type']) {
            // Lieux jamais visités avant le début du défi
            'nouveaux_lieux' => $user->checkins()
                ->whereBetween('happened_at', $window)
                ->whereNotIn(
                    'venue_id',
                    $user->checkins()
                        ->where('happened_at', '<', $challenge->starts_at)
                        ->select('venue_id'),
                )
                ->distinct()
                ->count('venue_id'),
            'checkins_count' => $user->checkins()
                ->whereBetween('happened_at', $window)
                ->count(),
            'virees_count' => $user->virees()
                ->whereNotNull('ended_at')
                ->whereBetween('ended_at', $window)
                ->count(),
            // min exprimé en km, distance stockée en m
            'km' => (int) floor(
                $user->virees()
                    ->whereNotNull('ended_at')
                    ->whereBetween('ended_at', $window)
                    ->sum('distance_m') / 1000,
            ),
            default => 0,
        };
    }
}
