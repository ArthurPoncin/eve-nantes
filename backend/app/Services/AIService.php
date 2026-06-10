<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AIService
{
    /**
     * Génère une courte narration de soirée à partir d'un contexte STRICTEMENT
     * typé (mood, venue, event, weather) — aucun texte libre utilisateur n'est
     * injecté dans le prompt (cf. PLAN §8, anti prompt-injection).
     *
     * Mise en cache 1h (cache:ai:{hash}). En l'absence de clé ou en cas d'échec
     * de l'API, retombe sur une narration locale déterministe : l'IA est un
     * enrichissement, pas une dépendance dure.
     *
     * @param  array{mood:string, venue:string, district?:string, event?:string, weather?:string}  $context
     */
    public function narrate(array $context): string
    {
        $key = (string) config('services.mistral.key', '');
        if ($key === '') {
            return $this->fallback($context);
        }

        return Cache::remember(
            'cache:ai:'.md5((string) json_encode($context)),
            now()->addHour(),
            fn (): string => $this->callMistral(
                $this->systemPrompt(),
                $this->userPrompt($context),
                $key,
            ) ?? $this->fallback($context),
        );
    }

    /**
     * Narration du récap d'une virée (suite de check-ins). Mêmes garde-fous
     * que narrate() : contexte strictement typé, cache 1h, fallback local.
     *
     * @param  array{venues: list<string>, moods: list<string>, distance_km: string, duration_min: int, weather?: string}  $context
     */
    public function narrateViree(array $context): string
    {
        $key = (string) config('services.mistral.key', '');
        if ($key === '') {
            return $this->vireeFallback($context);
        }

        return Cache::remember(
            'cache:ai:'.md5((string) json_encode($context)),
            now()->addHour(),
            fn (): string => $this->callMistral(
                $this->vireeSystemPrompt(),
                $this->vireeUserPrompt($context),
                $key,
            ) ?? $this->vireeFallback($context),
        );
    }

    private function callMistral(string $systemPrompt, string $userPrompt, string $key): ?string
    {
        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(8)
                ->post((string) config('services.mistral.endpoint'), [
                    'model' => config('services.mistral.model'),
                    'temperature' => 0.7,
                    'max_tokens' => 160,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $text = trim((string) $response->json('choices.0.message.content', ''));

            return $text !== '' ? $text : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function systemPrompt(): string
    {
        return 'Tu es le guide nocturne de NOCTAMBULE à Nantes. '
            .'Écris une seule phrase courte (30 mots maximum), évocatrice et chaleureuse, '
            .'en français, sans emoji ni guillemets, sur la soirée proposée.';
    }

    /**
     * @param  array{mood:string, venue:string, district?:string, event?:string, weather?:string}  $context
     */
    private function userPrompt(array $context): string
    {
        $parts = ['Ambiance: '.$context['mood'], 'Lieu: '.$context['venue']];

        if (! empty($context['district'])) {
            $parts[] = 'Quartier: '.$context['district'];
        }
        if (! empty($context['event'])) {
            $parts[] = 'Événement: '.$context['event'];
        }
        if (! empty($context['weather'])) {
            $parts[] = 'Météo: '.$context['weather'];
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param  array{mood:string, venue:string, district?:string, event?:string, weather?:string}  $context
     */
    private function fallback(array $context): string
    {
        $event = ! empty($context['event']) ? " pour {$context['event']}" : '';
        $weather = ! empty($context['weather']) ? ", ciel {$context['weather']}" : '';

        return "Ce soir, cap sur {$context['venue']}{$event}{$weather} — l'ambiance {$context['mood']} t'attend.";
    }

    private function vireeSystemPrompt(): string
    {
        return 'Tu es le guide nocturne de NOCTAMBULE à Nantes. '
            .'Résume cette virée nocturne en deux phrases courtes maximum, '
            .'chaleureuses et évocatrices, en français, sans emoji ni guillemets.';
    }

    /**
     * @param  array{venues: list<string>, moods: list<string>, distance_km: string, duration_min: int, weather?: string}  $context
     */
    private function vireeUserPrompt(array $context): string
    {
        $parts = [
            'Étapes dans l\'ordre: '.implode(', ', $context['venues']),
            'Ambiances: '.implode(', ', $context['moods']),
            'Distance à pied: '.$context['distance_km'].' km',
            'Durée: '.$context['duration_min'].' minutes',
        ];

        if (! empty($context['weather'])) {
            $parts[] = 'Météo: '.$context['weather'];
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param  array{venues: list<string>, moods: list<string>, distance_km: string, duration_min: int, weather?: string}  $context
     */
    private function vireeFallback(array $context): string
    {
        $venues = $context['venues'];
        $count = count($venues);

        if ($count === 0) {
            return 'Une virée éclair dans la nuit nantaise — la prochaine sera la bonne.';
        }

        if ($count === 1) {
            return "Une nuit fidèle à {$venues[0]} — parfois, un seul lieu suffit.";
        }

        $first = $venues[0];
        $last = $venues[$count - 1];

        return "De {$first} à {$last}, {$count} étapes et {$context['distance_km']} km "
            .'dans la nuit nantaise — une virée qui compte.';
    }
}
