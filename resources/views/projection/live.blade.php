@extends('layouts.projection')

@section('title', 'Proyección en vivo')

@php
    $fingerprint = implode('|', [$state['eventStatus'], $state['roundNumber'], $state['presentationId'], $state['presentationStatus'], $state['publicVoteCount'], $state['jurorVoteCount'], $state['currentActorHasVoted'] ? 'True' : 'False', $state['timerIsPaused'] ? 'True' : 'False', $state['participantFingerprint']]);
    $timer = $state['timerRemainingSeconds'] === null ? '--:--' : sprintf('%02d:%02d', intdiv($state['timerRemainingSeconds'], 60), $state['timerRemainingSeconds'] % 60);
@endphp

@section('content')
<main class="projection-stage" data-live-poll="{{ route('projection.state', $event->code) }}" data-results-redirect="{{ route('projection.ranking', $event->code) }}?transition=projection" data-poll-interval="3000" data-juror-total="{{ $state['jurorTotal'] }}" data-state="{{ $fingerprint }}">
    <section class="projection-join">
        <span class="eyebrow">Código del evento</span>
        <div class="event-code">{{ $event->code }}</div>
        <div class="qr-frame"><img src="{{ route('event.qr', $event->code) }}" alt="QR para acceder al evento {{ $event->code }}"></div>
        <div class="projection-cta"><span class="material-symbols-outlined">qr_code_scanner</span> Escanea o elige cómo participar</div>
        <div class="projection-entry-actions" aria-label="Accesos para votar">
            <a class="projection-entry-button public-entry" href="{{ route('event.code', $event->code) }}">
                <span class="material-symbols-outlined">how_to_vote</span><span><small>Público</small>Entrar a votar</span>
            </a>
            <a class="projection-entry-button jury-entry" href="{{ route('jury.access', ['event' => $event->code]) }}">
                <span class="material-symbols-outlined">gavel</span><span><small>Jurado</small>Acceso de jurados</span>
            </a>
        </div>
    </section>

    <section class="projection-center">
        <div class="projection-participant">
            <span class="badge badge-live">Ronda {{ $state['roundNumber'] }} · {{ $state['eventStatus'] === 'Paused' ? 'Evento en pausa' : ($state['presentationStatus'] === 'VotingOpen' ? 'Votación abierta' : ($state['presentationStatus'] === 'OnStage' ? 'Equipo activo en escenario' : 'En espera')) }}</span>
            <h1>{{ $state['participantName'] ?: $event->name }}</h1>
            <h2>{{ $state['projectTitle'] ?: 'La próxima presentación comenzará en breve' }}</h2>
            @if($state['presentationId'])<x-participant-details :participant="$state" dark expanded />@endif
        </div>
        <div class="projection-timer">
            <small>Tiempo restante</small>
            @if($state['timerEndsAt'])<strong data-countdown="{{ $state['timerEndsAt'] }}">{{ $timer }}</strong>@else<strong data-paused-timer>{{ $timer }}</strong>@endif
        </div>
    </section>

    <aside class="projection-monitor">
        <article class="projection-monitor-card">
            <div class="projection-monitor-top"><h3><span class="material-symbols-outlined">groups</span> Público</h3><strong data-public-count>{{ $state['publicVoteCount'] }}</strong></div>
            <div class="projection-track"><span data-public-track style="width:{{ min(100, $state['publicVoteCount'] * 5) }}%"></span></div>
            <p>votos registrados</p>
        </article>
        <article class="projection-monitor-card">
            <div class="projection-monitor-top"><h3><span class="material-symbols-outlined">gavel</span> Jurado</h3><strong><span data-jury-count>{{ $state['jurorVoteCount'] }}</span>/{{ $state['jurorTotal'] }}</strong></div>
            <div class="projection-track"><span data-jury-track style="width:{{ $state['jurorTotal'] ? $state['jurorVoteCount'] * 100 / $state['jurorTotal'] : 0 }}%"></span></div>
            <p>{{ max(0, $state['jurorTotal'] - $state['jurorVoteCount']) }} evaluaciones pendientes</p>
        </article>
    </aside>
</main>
@endsection
