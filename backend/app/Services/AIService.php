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
            fn (): string => $this->callMistral($context, $key) ?? $this->fallback($context),
        );
    }

    private function callMistral(array $context, string $key): ?string
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
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($context)],
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
}
