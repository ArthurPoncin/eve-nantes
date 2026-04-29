@extends('layouts.app')

@section('title', 'Accueil')

@section('content')
    <h2>Prochains evenements a Nantes</h2>

    @forelse($nextEvents as $event)
        <article class="card">
            <h3><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></h3>
            <p class="muted">{{ $event->starts_at?->format('d/m/Y H:i') }} - {{ $event->venue->name ?? 'Lieu a confirmer' }}</p>
        </article>
    @empty
        <p>Aucun evenement a venir.</p>
    @endforelse
@endsection

