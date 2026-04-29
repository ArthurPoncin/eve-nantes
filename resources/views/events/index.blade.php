@extends('layouts.app')

@section('title', 'Evenements')

@section('content')
    <h2>Tous les evenements</h2>

    @foreach($events as $event)
        <article class="card">
            <h3><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></h3>
            <p class="muted">{{ $event->starts_at?->format('d/m/Y H:i') }} - {{ $event->venue->name ?? 'Lieu a confirmer' }}</p>
        </article>
    @endforeach

    {{ $events->links() }}
@endsection

