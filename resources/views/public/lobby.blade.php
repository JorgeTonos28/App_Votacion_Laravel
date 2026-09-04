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
        $state['currentActorHasVoted'] ? 'true' : 'false',
        $state['timerIsPaused'] ? 'true' : 'false',
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

<main class="mobile-content" data-live-poll="{{ route('public.state') }}" data-results-redirect="{{ route('projection.ranking', $event->code) }}?transition=lobby" data-poll-interval="3000" data-state="{{ $fingerprint }}">
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
                    <div class="timer" data-countdown="{{ $state['timerEndsAt'] }}" data-timer-remaining="{{ $state['timerRemainingSeconds'] }}" data-timer-paused="{{ $state['timerIsPaused'] ? '1' : '0' }}">{{ $timer }}</div>
                @else
                    <div class="timer" data-paused-timer data-timer-remaining="{{ $state['timerRemainingSeconds'] }}" data-timer-paused="1">{{ $timer }}</div>
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

<!-- Modal de Detalle del Equipo (Solo accesible cuando NO está la votación abierta) -->
<div class="modal-backdrop" id="public-team-modal" style="display: none;">
    <div class="modal-card team-showcase-modal" style="max-width: 540px; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(4, 46, 128, 0.28), 0 0 0 1px rgba(4, 46, 128, 0.08);">
        <div class="modal-header team-modal-header" style="background: linear-gradient(135deg, #042E80 0%, #0C58C7 100%); color: #ffffff; padding: 22px 24px; position: relative; border-bottom: none;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="team-badge-pill" id="modal-team-number" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.35); color: #ffffff; padding: 3px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">groups</span> #1
                        </span>
                        <span class="team-area-pill" id="modal-team-area" style="background: #FEA203; color: #17233D; padding: 3px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(254, 162, 3, 0.35);">
                            Innovación
                        </span>
                    </div>
                    <h2 id="modal-team-name" style="margin: 12px 0 0; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; line-height: 1.2;">
                        Detalle del Equipo
                    </h2>
                </div>
                <button type="button" class="team-modal-close" data-modal-close="public-team-modal" style="background: rgba(255, 255, 255, 0.15); border: none; color: #ffffff; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;">
                    <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                </button>
            </div>
        </div>

        <div class="modal-body stack" style="gap: 18px; padding: 24px; background: #ffffff;">
            <!-- Caja de Proyecto Destacado -->
            <div class="team-project-card" style="background: linear-gradient(135deg, #F4F7FB 0%, #EBF2FC 100%); border: 1px solid #D5E2F5; border-left: 5px solid #FEA203; border-radius: 14px; padding: 16px 18px;">
                <div style="display: flex; align-items: center; gap: 6px; color: #D97706; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 4px;">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #FEA203;">lightbulb</span> Propuesta / Proyecto
                </div>
                <strong id="modal-team-project" style="font-size: 18px; color: #042E80; font-weight: 700; line-height: 1.35; display: block;"></strong>
            </div>

            <!-- Integrantes del Equipo -->
            <div id="modal-team-members-box">
                <div style="display: flex; align-items: center; gap: 6px; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #0C58C7;">badge</span> Integrantes del Equipo
                </div>
                <div id="modal-team-members-list" class="team-members-chips" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <!-- Se llena con chips interactivos con avatar -->
                </div>
            </div>

            <!-- Descripción de la Solución -->
            <div>
                <div style="display: flex; align-items: center; gap: 6px; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #042E80;">format_quote</span> Descripción de la Solución
                </div>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 14px 16px;">
                    <p id="modal-team-desc" style="margin: 0; font-size: 14px; line-height: 1.6; color: #334155; font-weight: 400;"></p>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 14px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0;">
            <button type="button" class="button button-secondary" data-modal-close="public-team-modal" style="display: inline-flex; align-items: center; gap: 6px; border-radius: 10px; font-weight: 600;">
                <span class="material-symbols-outlined" style="font-size: 18px;">check</span> Entendido
            </button>
        </div>
    </div>
</div>
@endsection
