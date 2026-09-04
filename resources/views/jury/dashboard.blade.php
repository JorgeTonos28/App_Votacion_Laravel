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
        $state['currentActorHasVoted'] ? 'True' : 'False',
        $state['timerIsPaused'] ? 'True' : 'False',
        $state['participantFingerprint']
    ]);
    $isVotingOpen = $state['presentationStatus'] === 'VotingOpen';
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
    <div class="modal-card" style="max-width: 520px;">
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
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Descripción del Proyecto</small>
                <p id="modal-team-desc" style="margin: 0; font-size: 14px; line-height: 1.5; color: var(--text);"></p>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 12px 16px;">
            <button type="button" class="button button-ghost" data-modal-close="public-team-modal">Cerrar</button>
        </div>
    </div>
</div>
@endsection
