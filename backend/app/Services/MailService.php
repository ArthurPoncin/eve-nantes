<?php

namespace App\Services;

use App\Models\Soiree;
use Illuminate\Support\Facades\Http;
use Throwable;

class MailService
{
    /**
     * Envoie une soirée par email via Resend. Retourne false si la clé est
     * absente ou si l'envoi échoue (le contrôleur traduit cela en erreur).
     *
     * La soirée doit avoir ses relations venue (et event) chargées.
     */
    public function shareSoiree(Soiree $soiree, string $email): bool
    {
        $key = (string) config('services.resend.key', '');
        if ($key === '') {
            return false;
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(10)
                ->post((string) config('services.resend.endpoint'), [
                    'from' => config('services.resend.from'),
                    'to' => [$email],
                    'subject' => 'Ta soirée à Nantes — '.$soiree->venue->name,
                    'html' => $this->html($soiree),
                ]);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function html(Soiree $soiree): string
    {
        $venue = $soiree->venue;
        $event = $soiree->event;
        $weather = $soiree->weather_snapshot;

        $url = rtrim((string) config('services.frontend.url'), '/').'/venues/'.$venue->slug;

        $narrative = e((string) $soiree->ai_narrative);
        $venueName = e($venue->name);
        $address = e((string) $venue->address_line);

        $eventBlock = $event
            ? '<p style="margin:16px 0 0;color:#b7aed1;font-size:14px;">À l\'affiche : <strong style="color:#ff5bae;">'.e($event->title).'</strong></p>'
            : '';

        $weatherBlock = is_array($weather) && isset($weather['condition'])
            ? '<p style="margin:8px 0 0;color:#6f6788;font-size:13px;">Météo : '.e((string) $weather['condition'])
                .(isset($weather['temp']) ? ' · '.e((string) round((float) $weather['temp'])).'°C' : '').'</p>'
            : '';

        return <<<HTML
        <div style="background:#07060b;padding:32px;font-family:Helvetica,Arial,sans-serif;">
          <div style="max-width:520px;margin:0 auto;background:#0e0c16;border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:28px;">
            <p style="margin:0;color:#6f6788;font-size:11px;letter-spacing:0.22em;text-transform:uppercase;">NOCTAMBULE · Nantes</p>
            <p style="margin:16px 0 0;color:#f5f1ff;font-size:22px;font-style:italic;line-height:1.4;">« {$narrative} »</p>
            <div style="margin-top:24px;border-top:1px solid rgba(255,255,255,0.08);padding-top:20px;">
              <p style="margin:0;color:#f5f1ff;font-size:20px;font-style:italic;">{$venueName}</p>
              <p style="margin:4px 0 0;color:#6f6788;font-size:13px;">{$address}</p>
              {$eventBlock}
              {$weatherBlock}
            </div>
            <a href="{$url}" style="display:inline-block;margin-top:24px;background:#ff2d92;color:#fff;text-decoration:none;padding:12px 22px;border-radius:9999px;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;">Voir le lieu</a>
          </div>
        </div>
        HTML;
    }
}
