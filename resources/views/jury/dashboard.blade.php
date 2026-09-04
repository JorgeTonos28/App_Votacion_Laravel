@extends('layouts.public')
@section('title', 'Panel del Jurado')

@php
    $completed = $history->whereIn('voteStatus', ['Submitted', 'Updated'])->count();
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
    $isVotingOpen = $state['presentationStatus'] === 'VotingOpen';
    $isOnStage = $state['presentationStatus'] === 'OnStage';
    $hasActivePresentation = !empty($state['presentationId']) && in_array($state['presentationStatus'], ['OnStage', 'VotingOpen'], true);
    $timer = $state['timerRemainingSeconds'] === null ? '--:--' : sprintf('%02d:%02d', intdiv($state['timerRemainingSeconds'], 60), $state['timerRemainingSeconds'] % 60);
@endphp

@section('content')
<header class="mobile-topbar">
    <a class="wordmark" href="{{ route('jury.dashboard') }}">
        <span class="material-symbols-outlined">leaderboard</span>
        <strong>InnovaMente</strong>
    </a>
    <span class="connection-pill">Conectado · {{ $juror->name }}</span>
</header>

<main class="mobile-content" data-live-poll="{{ route('jury.state') }}" data-results-redirect="{{ route('projection.ranking', $event->code) }}?transition=lobby" data-poll-interval="4000" data-state="{{ $fingerprint }}">
    @if($isVotingOpen && !$state['currentActorHasVoted'])
        <div class="notice-banner" style="background: rgba(34, 197, 94, 0.15); border-left: 4px solid var(--success, #22c55e);">
            <span class="material-symbols-outlined" style="color: var(--success, #22c55e); font-size: 32px;">campaign</span>
            <div>
                <strong style="font-size: 16px; color: var(--text);">¡Evaluación en curso!</strong>
                <br>La rúbrica para <strong>{{ $state['participantName'] }}</strong> ya está disponible.
            </div>
        </div>
    @endif

    @if($hasActivePresentation)
        <!-- Tarjeta de Tiempo / Cronómetro en Vivo para Jurados -->
        <section class="card timer-card" style="margin-bottom: 16px; border-left: 4px solid {{ $isVotingOpen ? 'var(--success, #22c55e)' : 'var(--primary, #042E80)' }};">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span class="eyebrow" style="margin: 0; color: {{ $isVotingOpen ? 'var(--success, #22c55e)' : 'var(--primary, #042E80)' }};">
                    {{ $isVotingOpen ? 'Tiempo restante de votación' : 'Tiempo de exposición / pitch' }}
                </span>
                <span class="badge {{ $isVotingOpen ? 'badge-live' : 'badge-warning' }}" style="font-size: 11px;">
                    {{ $isVotingOpen ? 'Votación abierta' : 'En escenario' }}
                </span>
            </div>

            @if($state['timerEndsAt'])
                <div class="timer" data-countdown="{{ $state['timerEndsAt'] }}" style="font-size: 2.7rem; font-weight: 800; margin: 0.5rem 0;">{{ $timer }}</div>
            @else
                <div class="timer" data-paused-timer style="font-size: 2.7rem; font-weight: 800; margin: 0.5rem 0;">{{ $timer }}</div>
            @endif

            <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 6px;">
                <span class="material-symbols-outlined" style="font-size: 16px;">schedule</span>
                @if($isVotingOpen)
                    Equipo actual: <strong>{{ $state['participantName'] }}</strong>
                @else
                    Presentando: <strong>{{ $state['participantName'] }}</strong>
                @endif
            </p>
        </section>
    @endif

    <section class="card jury-progress">
        <span class="eyebrow">Progreso de evaluación · Ronda {{ $event->current_round }}</span>
        <div>
            <strong>{{ $completed }}</strong> / {{ $history->count() }}
            <small>equipos evaluados</small>
        </div>
        <div class="ballot-progress" style="--progress:{{ $history->count() ? $completed * 100 / $history->count() : 0 }}%"></div>
    </section>

    <div style="display: flex; justify-content: space-between; align-items: center; margin: 18px 0 10px;">
        <h2 style="margin: 0; font-size: 17px;">Equipos Asignados</h2>
        @if(!$isVotingOpen)
            <small style="color: var(--text-muted);">Toca un equipo para ver detalles</small>
        @endif
    </div>

    <div class="team-list stack" style="gap: 10px;">
        @foreach($history as $row)
            @php
                $isCurrent = $row['participantName'] === $state['participantName'];
                $isDone = in_array($row['voteStatus'], ['Submitted', 'Updated'], true);
                $teamJson = json_encode([
                    'name' => $row['participantName'],
                    'number' => $row['participantNumber'] ?? 1,
                    'project' => $row['projectTitle'] ?: 'Proyecto de innovación',
                    'area' => $row['participantArea'] ?: 'General',
                    'members' => $row['participantMembers'] ?? [],
                    'description' => $row['participantDescription'] ?: 'Sin descripción detallada.',
                ], JSON_HEX_APOS | JSON_HEX_QUOT);
            @endphp
            <article class="team-row {{ $isCurrent ? 'is-current' : '' }}" style="padding: 12px 14px;">
                <div class="team-row-main" @if(!$isVotingOpen) data-team-modal='{{ $teamJson }}' style="cursor: pointer;" @endif>
                    <span class="status-dot {{ $isDone ? 'done' : '' }}">
                        <span class="material-symbols-outlined">{{ $isDone ? 'check_circle' : ($isCurrent ? 'play_circle' : 'schedule') }}</span>
                    </span>
                    <div>
                        <strong style="font-size: 15px;">{{ $row['participantName'] }}</strong>
                        <div class="table-subtitle">
                            {{ $row['projectTitle'] ?: 'Proyecto sin título' }} ·
                            <span style="font-weight: 600; color: {{ $isDone ? 'var(--success)' : ($isCurrent ? 'var(--primary)' : 'var(--text-muted)') }};">
                                {{ $isDone ? 'Evaluado' : ($isCurrent ? ($isVotingOpen ? 'Votación abierta' : 'En escenario') : 'Pendiente') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Botón de detalles: solo disponible cuando NO está la votación abierta -->
                    @if(!$isVotingOpen)
                        <button type="button" class="button button-sm button-ghost" data-team-modal='{{ $teamJson }}' title="Ver detalles y miembros">
                            <span class="material-symbols-outlined" style="font-size: 16px;">info</span> Detalles
                        </button>
                    @endif

                    @if($isCurrent && $isVotingOpen)
                        <a class="button button-accent" href="{{ route('jury.ballot') }}" style="font-weight: 700;">
                            <span class="material-symbols-outlined">how_to_vote</span> {{ $isDone ? 'Editar' : 'Evaluar' }}
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</main>

<nav class="mobile-nav" aria-label="Navegación del jurado">
    <a class="is-active" href="{{ route('jury.dashboard') }}">
        <span class="material-symbols-outlined">gavel</span>
        <span>Jurado</span>
    </a>
    @if($isVotingOpen)
        <a class="nav-highlight" href="{{ route('jury.ballot') }}">
            <span class="material-symbols-outlined">how_to_vote</span>
            <span>Evaluar</span>
        </a>
    @else
        <span class="mobile-nav-disabled">
            <span class="material-symbols-outlined">how_to_vote</span>
            <span>Votar</span>
        </span>
    @endif
    <a href="{{ route('projection.live', $event->code) }}" target="_blank">
        <span class="material-symbols-outlined">live_tv</span>
        <span>Proyección</span>
    </a>
    <form class="mobile-nav-form" action="{{ route('jury.logout') }}" method="post">
        @csrf
        <button class="mobile-nav-btn" type="submit" title="Cerrar sesión">
            <span class="material-symbols-outlined">logout</span>
            <span>Salir</span>
        </button>
    </form>
</nav>

<!-- Modal Genérico de Detalle del Equipo para Jurados -->
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
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #042E80;">format_quote</span> Descripción del Proyecto
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
