<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ingestion quotidienne de l'open-data évènements de Nantes Métropole.
Schedule::command('events:import')->dailyAt('04:00');

