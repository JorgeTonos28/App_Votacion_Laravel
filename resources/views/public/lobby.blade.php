@extends('layouts.public')
@section('title', $event->name)

@php
    $fingerprint = implode('|', [
        $state['eventStatus'],
        $state['roundNumber'],
        $state['presentationId'],
        $state['presentationStatus'],
        $state['publicVoteCount'],
        $state['jurorVoteCount'],
        $state['currentActorHasVoted'] ? 'True' : 'False',
        $state['timerIsPaused'] ? 'True' : 'False',
        $state['participantFingerprint']
    ]);
    $timer = $state['timerRemainingSeconds'] === null ? '--:--' : sprintf('%02d:%02d', intdiv($state['timerRemainingSeconds'], 60), $state['timerRemainingSeconds'] % 60);
    $isVotingOpen = $state['presentationStatus'] === 'VotingOpen';
@endphp

@section('content')
<header class="mobile-topbar">
    <a class="wordmark" href="{{ route('public.lobby') }}">
        <span class="material-symbols-outlined">leaderboard</span>
        <strong>InnovaMente</strong>
    </a>
    <span class="connection-pill">Conectado</span>
</header>

<main class="mobile-content" data-live-poll="{{ route('public.state') }}" data-results-redirect="{{ route('projection.ranking', $event->code) }}?transition=lobby" data-poll-interval="4000" data-state="{{ $fingerprint }}">
    <section class="event-heading">
        <h1>{{ $event->name }}</h1>
        <span class="badge {{ $isVotingOpen ? 'badge-live' : 'badge-warning' }}">
            {{ match($state['presentationStatus']) {
                'VotingOpen' => 'Votación abierta',
                'OnStage' => 'Presentación en curso',
                'VotingClosed' => 'Votación cerrada',
                default => 'Próxima presentación'
            } }}
        </span>
    </section>

    @if(!$state['presentationId'])
        <section class="card identity-card">
            <div class="identity-avatar">
                <span class="material-symbols-outlined">hourglass_top</span>
            </div>
            <h2>La próxima presentación comenzará en breve</h2>
            <p>El panel se actualizará automáticamente cuando el operador dé paso al siguiente equipo.</p>
        </section>

        <!-- Lista de equipos participantes (disponible mientras se espera) -->
        @if(isset($participants) && $participants->isNotEmpty() && !$isVotingOpen)
            <section style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h2 style="font-size: 16px; margin: 0;">Equipos Participantes</h2>
                    <span class="badge badge-subtle">{{ $participants->count() }} equipos</span>
                </div>
                <div class="team-list stack" style="gap: 10px;">
                    @foreach($participants as $participant)
                        @php
                            $teamJson = json_encode([
                                'name' => $participant->name,
                                'number' => $participant->number,
                                'project' => $participant->project_title ?: 'Propuesta de innovación',
                                'area' => $participant->area ?: 'General',
                                'members' => $participant->member_names ?? [],
                                'description' => $participant->description ?: 'Sin descripción detallada.',
                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        @endphp
                        <article class="team-row" style="cursor: pointer;" data-team-modal='{{ $teamJson }}'>
                            <div class="team-row-main">
                                <span class="status-dot">
                                    <span class="material-symbols-outlined">group</span>
                                </span>
                                <div>
                                    <strong>{{ $participant->name }}</strong>
                                    <div class="table-subtitle">{{ $participant->project_title ?: 'Proyecto de innovación' }}</div>
                                </div>
                            </div>
                            <button type="button" class="button button-sm button-ghost" style="padding: 4px 8px;" data-team-modal='{{ $teamJson }}'>
                                <span class="material-symbols-outlined" style="font-size: 16px;">info</span> Detalles
                            </button>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

    @else
        <!-- Equipo activo -->
        <section class="card participant-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span class="eyebrow" style="margin: 0;">Equipo activo</span>
                @if(!$isVotingOpen)
                    @php
                        $activeJson = json_encode([
                            'name' => $state['participantName'],
                            'number' => $state['participantNumber'],
                            'project' => $state['projectTitle'] ?: 'Propuesta de innovación',
                            'area' => $state['participantArea'] ?: 'General',
                            'members' => $state['participantMembers'] ?? [],
                            'description' => $state['participantDescription'] ?: 'Sin descripción detallada.',
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    @endphp
                    <button type="button" class="button button-sm button-secondary" data-team-modal='{{ $activeJson }}' style="padding: 4px 10px; font-size: 12px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">info</span> Ver Detalles Completos
                    </button>
                @endif
            </div>

            <h2>{{ $state['participantName'] }}</h2>
            <div class="project-box">
                <strong>Proyecto</strong>
                <p>{{ $state['projectTitle'] ?: 'Propuesta de innovación' }}</p>
            </div>

            <!-- Resumen básico de integrantes si está presentando -->
            @if(!$isVotingOpen)
                <x-participant-details :participant="$state" />
            @endif

            <span class="badge badge-warning" style="margin-top: .8rem">
                {{ $state['presentationStatus'] === 'OnStage' ? 'Presentación en curso' : ($isVotingOpen ? 'Votación abierta' : 'Evaluación') }}
            </span>
        </section>

        @if($state['presentationStatus'] === 'OnStage')
            <section class="card timer-card">
                <span class="eyebrow">Tiempo de exposición</span>
                @if($state['timerEndsAt'])
                    <div class="timer" data-countdown="{{ $state['timerEndsAt'] }}">{{ $timer }}</div>
                @else
                    <div class="timer" data-paused-timer>{{ $timer }}</div>
                @endif
                <p><span class="material-symbols-outlined">schedule</span> La votación se abrirá al finalizar la exposición.</p>
            </section>

            <div class="lobby-action">
                <button class="button button-block" disabled>
                    <span class="material-symbols-outlined">how_to_vote</span> Votar
                </button>
                <small>Debes esperar a que finalice la presentación para emitir tu voto.</small>
            </div>

            <!-- Lista de demás equipos si desea curiosear durante la presentación -->
            @if(isset($participants) && $participants->isNotEmpty() && !$isVotingOpen)
                <section style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h2 style="font-size: 15px; margin: 0; color: var(--text-muted);">Todos los Equipos Participantes</h2>
                    </div>
                    <div class="team-list stack" style="gap: 8px;">
                        @foreach($participants as $participant)
                            @php
                                $teamJson = json_encode([
                                    'name' => $participant->name,
                                    'number' => $participant->number,
                                    'project' => $participant->project_title ?: 'Propuesta de innovación',
                                    'area' => $participant->area ?: 'General',
                                    'members' => $participant->member_names ?? [],
                                    'description' => $participant->description ?: 'Sin descripción detallada.',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <article class="team-row {{ $participant->name === $state['participantName'] ? 'is-current' : '' }}" style="cursor: pointer; padding: 10px 14px;" data-team-modal='{{ $teamJson }}'>
                                <div class="team-row-main">
                                    <span class="status-dot {{ $participant->name === $state['participantName'] ? 'done' : '' }}">
                                        <span class="material-symbols-outlined">{{ $participant->name === $state['participantName'] ? 'play_circle' : 'group' }}</span>
                                    </span>
                                    <div>
                                        <strong style="font-size: 14px;">{{ $participant->name }}</strong>
                                        <div class="table-subtitle" style="font-size: 12px;">{{ $participant->project_title ?: 'Proyecto de innovación' }}</div>
                                    </div>
                                </div>
                                <button type="button" class="button button-sm button-ghost" style="padding: 2px 6px; font-size: 12px;" data-team-modal='{{ $teamJson }}'>
                                    <span class="material-symbols-outlined" style="font-size: 15px;">info</span>
                                </button>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

        @elseif($isVotingOpen && !$state['currentActorHasVoted'])
            <!-- Cuando la votación está ABIERTA: Enfoque 100% en votar, sin distracciones de detalles -->
            <section class="notice-banner" style="background: rgba(34, 197, 94, 0.15); border-left: 4px solid var(--success, #22c55e);">
                <span class="material-symbols-outlined" style="color: var(--success, #22c55e); font-size: 32px;">campaign</span>
                <div>
                    <strong style="font-size: 16px; color: var(--text);">¡La votación está abierta!</strong>
                    <br>Evalúa ahora al equipo <strong>{{ $state['participantName'] }}</strong> antes de que expire el tiempo.
                </div>
            </section>

            <a class="button button-accent button-block" href="{{ route('public.ballot') }}" style="font-size: 18px; padding: 16px; font-weight: 700;">
                <span class="material-symbols-outlined" style="font-size: 24px;">how_to_vote</span> Votar ahora
            </a>

        @elseif($state['currentActorHasVoted'])
            <section class="card identity-card">
                <div class="success-icon">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <h2>Ya registraste tu voto</h2>
                <p>Quédate aquí; la pantalla cambiará cuando comience la siguiente presentación.</p>
            </section>
        @else
            <section class="card identity-card">
                <div class="identity-avatar">
                    <span class="material-symbols-outlined">lock_clock</span>
                </div>
                <h2>La votación ha finalizado</h2>
                <p>Espera la próxima presentación. El estado se actualizará automáticamente.</p>
            </section>
        @endif
    @endif
</main>

<nav class="mobile-nav" aria-label="Navegación del evento">
    <a class="is-active" href="{{ route('public.lobby') }}">
        <span class="material-symbols-outlined">event</span>
        <span>Evento</span>
    </a>
    @if($isVotingOpen && !$state['currentActorHasVoted'])
        <a class="nav-highlight" href="{{ route('public.ballot') }}">
            <span class="material-symbols-outlined">how_to_vote</span>
            <span>Votar</span>
        </a>
    @else
        <span class="mobile-nav-disabled">
            <span class="material-symbols-outlined">how_to_vote</span>
            <span>Votar</span>
        </span>
    @endif
    <a href="{{ route('jury.access', ['eventCode' => $event->code]) }}">
        <span class="material-symbols-outlined">gavel</span>
        <span>Jurado</span>
    </a>
    <a href="{{ route('projection.live', $event->code) }}" target="_blank">
        <span class="material-symbols-outlined">live_tv</span>
        <span>Proyección</span>
    </a>
</nav>

<!-- Modal Genérico de Detalle del Equipo (Solo accesible cuando NO está la votación abierta) -->
<div class="modal-backdrop" id="public-team-modal" style="display: none;">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <div>
                <span class="badge badge-subtle" id="modal-team-number">#1</span>
                <h3 id="modal-team-name" style="margin-top: 4px;">Detalle del Equipo</h3>
            </div>
            <button type="button" class="icon-button" data-modal-close="public-team-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="modal-body stack" style="gap: 14px;">
            <div style="background: var(--surface-subtle); padding: 12px 14px; border-radius: 8px;">
                <small style="color: var(--text-muted); display: block; margin-bottom: 2px;">Título del Proyecto</small>
                <strong id="modal-team-project" style="font-size: 15px; color: var(--text);"></strong>
            </div>

            <div style="background: var(--surface-subtle); padding: 12px 14px; border-radius: 8px;">
                <small style="color: var(--text-muted); display: block; margin-bottom: 2px;">Área o Categoría</small>
                <span id="modal-team-area" style="font-size: 14px; color: var(--text); font-weight: 500;"></span>
            </div>

            <div id="modal-team-members-box" style="background: var(--surface-subtle); padding: 12px 14px; border-radius: 8px;">
                <small style="color: var(--text-muted); display: block; margin-bottom: 6px;">Integrantes del Equipo</small>
                <ul id="modal-team-members-list" style="margin: 0; padding-left: 18px; font-size: 14px; color: var(--text);"></ul>
            </div>

            <div>
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Descripción de la Solución</small>
                <p id="modal-team-desc" style="margin: 0; font-size: 14px; line-height: 1.5; color: var(--text);"></p>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 12px 16px;">
            <button type="button" class="button button-ghost" data-modal-close="public-team-modal">Cerrar</button>
        </div>
    </div>
</div>
@endsection
