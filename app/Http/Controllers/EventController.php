<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::query()
            ->with(['venue', 'categories'])
            ->where('is_published', true)
            ->orderBy('starts_at')
            ->paginate(12);

        return view('events.index', compact('events'));
    }

    public function show(string $slug): View
    {
        $event = Event::query()
            ->with(['venue', 'organizer', 'categories'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('events.show', compact('event'));
    }
}

