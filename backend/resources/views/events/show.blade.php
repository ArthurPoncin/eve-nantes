@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <article class="card">
        <h2>{{ $event->title }}</h2>
        <p class="muted">{{ $event->starts_at?->format('d/m/Y H:i') }} - {{ $event->venue->name ?? 'Lieu a confirmer' }}</p>
        <p>{{ $event->description }}</p>
    </article>
@endsection
