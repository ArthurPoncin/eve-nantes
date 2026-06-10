<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Support\Carbon;

class BadgeService
{
    /**
     * Tous les badges, marqués débloqués ou non pour cet utilisateur.
     *
     * @return list<array{id: string, label: string, description: string, icon: string, unlocked: bool, unlocked_at: string|null}>
     */
    public function forUser(User $user): array
    {
        $unlockedAt = $user->badges->mapWithKeys(fn (Badge $badge) => [
            $badge->id => Carbon::parse($badge->pivot->unlocked_at)->toIso8601String(),
        ]);

        return Badge::orderBy('id')->get()->map(fn (Badge $badge): array => [
            'id' => $badge->id,
            'label' => $badge->label,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'unlocked' => $unlockedAt->has($badge->id),
            'unlocked_at' => $unlockedAt->get($badge->id),
        ])->all();
    }

    /**
     * Évalue les critères et attribue les badges nouvellement débloqués.
     * Appelé après chaque action qui peut en débloquer (partage, avis).
     *
     * @return list<string> ids des badges qui viennent d'être débloqués
     */
    public function evaluate(User $user): array
    {
        $owned = $user->badges()->pluck('badges.id')->all();
        $newlyUnlocked = [];

        foreach (Badge::all() as $badge) {
            if (in_array($badge->id, $owned, true)) {
                continue;
            }

            if ($this->meetsCriteria($user, $badge->criteria)) {
                $user->badges()->attach($badge->id, ['unlocked_at' => now()]);
                $newlyUnlocked[] = $badge->id;
            }
        }

        return $newlyUnlocked;
    }

    /**
     * @param  array{type: string, min: int}  $criteria
     */
    private function meetsCriteria(User $user, array $criteria): bool
    {
        $min = (int) $criteria['min'];

        return match ($criteria['type']) {
            // 5 soirées composées et partagées
            'soirees_count' => $user->soirees()->count() >= $min,
            // 3 lieux différents écumés
            'distinct_venues' => $user->soirees()->distinct()->count('venue_id') >= $min,
            // 3 ambiances différentes vécues
            'distinct_moods' => $user->soirees()->distinct()->count('mood') >= $min,
            // 3 soirées au même lieu — havingRaw : PostgreSQL n'accepte pas
            // un alias de SELECT dans le HAVING (sqlite si).
            'same_venue' => $user->soirees()
                ->select('venue_id')
                ->groupBy('venue_id')
                ->havingRaw('count(*) >= ?', [$min])
                ->exists(),
            // Premier avis posté
            'reviews_count' => $user->reviews()->count() >= $min,
            default => false,
        };
    }
}
