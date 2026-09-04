@extends('layouts.admin')
@section('title', 'Mesa de Control en Vivo')
@section('admin-content-class', 'is-compact')

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
@endphp

@section('content')
<!-- Barra de Control y Acceso Rápido -->
<div class="live-toolbar" style="background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
        <span class="pulse-indicator" style="display:inline-block; width:12px; height:12px; border-radius:50%; background:{{ $event->status === 'Live' ? '#22c55e' : ($event->status === 'Paused' ? '#f59e0b' : '#3b82f6') }};"></span>
        <div>
            <strong style="font-size: 17px; color: var(--text);">{{ $event->name }}</strong>
            <span style="color: var(--text-muted); font-size: 14px; margin-left: 6px;">Código <code>{{ $event->code }}</code></span>
        </div>
        <span class="badge {{ $event->status === 'Live' ? 'badge-live' : ($event->status === 'Paused' ? 'badge-warning' : 'badge-primary') }}">
            {{ match($event->status) {
                'Live' => 'EN VIVO',
                'LobbyOpen' => 'LOBBY ABIERTO',
                'Paused' => 'EN PAUSA',
                'Finished' => 'FINALIZADO',
                'Published' => 'PUBLICADO',
                default => $event->status
            } }}
        </span>
        <span class="badge badge-subtle">Ronda {{ $event->current_round }}</span>
    </div>

    <div class="page-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <a class="button button-sm button-secondary" target="_blank" href="{{ route('projection.live', $event->code) }}" title="Abrir pantalla para proyectores o streaming">
            <span class="material-symbols-outlined">open_in_new</span> Proyección
        </a>

        @if($event->status === 'Published')
            <a class="button button-sm button-accent" target="_blank" href="{{ route('projection.ranking', $event->code) }}">
                <span class="material-symbols-outlined">leaderboard</span> Ver resultados
            </a>
        @else
            <a class="button button-sm button-secondary" href="{{ route('admin.event.results', $event) }}">
                <span class="material-symbols-outlined">leaderboard</span> Gestionar resultados
            </a>
        @endif

        @if($event->status === 'Live')
            <form action="{{ route('admin.control', [$event, 'pause']) }}" method="post" style="display: inline;">
                @csrf
                <button class="button button-sm button-secondary" type="submit" title="Pausar cronómetro y votación">
                    <span class="material-symbols-outlined">pause</span> Pausar
                </button>
            </form>
        @elseif($event->status === 'Paused')
            <form action="{{ route('admin.control', [$event, 'resume']) }}" method="post" style="display: inline;">
                @csrf
                <button class="button button-sm button-accent" type="submit" title="Reanudar evento">
                    <span class="material-symbols-outlined">play_arrow</span> Reanudar
                </button>
            </form>
        @endif

        @if(in_array($event->status, ['Live', 'Paused']))
            <form action="{{ route('admin.control', [$event, 'finish']) }}" method="post" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas finalizar el evento? Ya no se recibirán nuevos votos.');">
                @csrf
                <button class="button button-sm button-outline" type="submit">
                    <span class="material-symbols-outlined">stop</span> Finalizar
                </button>
            </form>
        @endif

        <!-- Botón para abrir modal de reinicio de ronda -->
        <button type="button" class="button button-sm button-danger" data-modal-open="restart-round-modal" title="Reiniciar ronda o avanzar a la siguiente">
            <span class="material-symbols-outlined">restart_alt</span> Reiniciar Ronda
        </button>
    </div>
</div>

<div class="live-grid" data-live-poll="{{ route('admin.live.state', $event) }}" data-poll-interval="3000" data-juror-total="{{ $state['jurorTotal'] }}" data-state="{{ $fingerprint }}">
    <!-- Columna 1: Cola de Presentaciones -->
    <section>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 style="margin: 0; font-size: 16px;">Orden de Presentación</h2>
            <span class="badge badge-subtle">{{ $participants->count() }} equipos</span>
        </div>

        <div class="card queue-list" style="max-height: 720px; overflow-y: auto;">
            @foreach($participants as $p)
                @php
                    $presentation = $p->presentation;
                    $isActive = $presentation?->id === $state['presentationId'];
                    $isComplete = in_array($presentation?->status, ['Scored', 'VotingClosed'], true);
                    $isDisabled = $p->status === 'Disqualified';
                    $itemClass = $isActive ? 'is-active' : ($isComplete ? 'is-complete' : ($isDisabled ? 'is-disabled' : ''));
                @endphp
                <div class="queue-item {{ $itemClass }}" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; gap: 10px; border-bottom: 1px solid var(--border-subtle);">
                    <form action="{{ route('admin.control', [$event, 'presentation']) }}" method="post" style="display: flex; align-items: center; gap: 12px; flex: 1;">
                        @csrf
                        <input type="hidden" name="participant_id" value="{{ $p->id }}">
                        <span class="material-symbols-outlined" style="color: {{ $isActive ? 'var(--primary)' : ($isComplete ? 'var(--success)' : ($isDisabled ? 'var(--danger)' : 'var(--text-muted)')) }}; font-size: 24px;">
                            {{ $isActive ? 'play_circle' : ($isComplete ? 'check_circle' : ($isDisabled ? 'block' : 'schedule')) }}
                        </span>
                        <button type="submit" style="border: 0; background: transparent; text-align: left; flex: 1; cursor: {{ $presentation?->status === 'Pending' && !$isDisabled ? 'pointer' : 'default' }};" @disabled($presentation?->status !== 'Pending' || $isDisabled)>
                            <strong style="font-size: 14px; color: var(--text); display: block;">{{ $p->name }}</strong>
                            <small style="color: var(--text-muted); font-size: 12px;">
                                #{{ $p->presentation_order }} · {{ $p->project_title ?: 'Sin título' }}
                            </small>
                        </button>
                    </form>

                    <div style="display: flex; align-items: center; gap: 6px;">
                        @if($isActive)
                            <span class="badge badge-sm badge-live">En Escenario</span>
                        @elseif($isComplete)
                            <span class="badge badge-sm badge-success">Evaluado</span>
                            <!-- Opción para reiniciar turno de este equipo -->
                            <form action="{{ route('admin.control', [$event, 'reset_turn']) }}" method="post" onsubmit="return confirm('¿Reiniciar el turno del equipo {{ $p->name }} a Pendiente para que vuelva a presentar?');">
                                @csrf
                                <input type="hidden" name="presentation_id" value="{{ $presentation->id }}">
                                <button type="submit" class="icon-button" title="Reiniciar turno a Pendiente" style="width: 28px; height: 28px;">
                                    <span class="material-symbols-outlined" style="font-size: 18px; color: var(--warning);">replay</span>
                                </button>
                            </form>
                        @elseif($isDisabled)
                            <span class="badge badge-sm badge-danger">Inhabilitado</span>
                        @else
                            <span class="badge badge-sm badge-muted">Pendiente</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Columna 2: Escenario y Centro de Mando -->
    <section>
        <article class="card control-stage" style="padding: 24px; position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span class="badge {{ $state['presentationStatus'] === 'VotingOpen' ? 'badge-live' : 'badge-warning' }}" style="font-size: 12px;">
                    {{ $state['presentationStatus'] === 'VotingOpen' ? 'VOTACIÓN EN VIVO' : ($state['presentationStatus'] === 'OnStage' ? 'EQUIPO EN ESCENARIO' : 'SALA DE ESPERA') }}
                </span>

                @if($state['presentationId'])
                    <!-- Acciones rápidas para el turno activo -->
                    <div style="display: flex; gap: 8px;">
                        <!-- Añadir 1 minuto extra (+60s) -->
                        <form action="{{ route('admin.control', [$event, 'add_time']) }}" method="post">
                            @csrf
                            <input type="hidden" name="presentation_id" value="{{ $state['presentationId'] }}">
                            <input type="hidden" name="seconds" value="60">
                            <button type="submit" class="button button-sm button-secondary" title="Extender tiempo de presentación en 1 minuto">
                                <span class="material-symbols-outlined" style="font-size: 16px;">more_time</span> +1 min
                            </button>
                        </form>

                        <!-- Reiniciar turno del equipo activo -->
                        <form action="{{ route('admin.control', [$event, 'reset_turn']) }}" method="post" onsubmit="return confirm('¿Deseas reiniciar el turno de este equipo? El cronómetro volverá a cero y su turno volverá a Pendiente.');">
                            @csrf
                            <input type="hidden" name="presentation_id" value="{{ $state['presentationId'] }}">
                            <button type="submit" class="button button-sm button-ghost" title="Reiniciar turno por problemas técnicos">
                                <span class="material-symbols-outlined" style="font-size: 16px; color: var(--warning);">replay</span> Reiniciar turno
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <h1 style="font-size: 26px; margin: 0 0 6px; color: var(--text);">
                {{ $state['participantName'] ?: 'Selecciona un equipo de la lista' }}
            </h1>
            <p style="font-size: 15px; color: var(--text-muted); margin: 0 0 18px;">
                {{ $state['projectTitle'] ?: 'El equipo seleccionado pasará al escenario y su cronómetro comenzará a contar.' }}
            </p>

            @if($state['presentationId'])
                <x-participant-details :participant="$state" />
            @endif

            <!-- Par de Relojes / Cronómetro Gigante -->
            <div class="timer-pair" style="margin: 20px 0;">
                <div class="timer-box">
                    <small>Hora de Sala</small>
                    <strong data-live-clock>{{ now()->setTimezone($event->time_zone ?: config('app.timezone', 'America/Santo_Domingo'))->format('H:i') }}</strong>
                </div>
                <div class="timer-box accent">
                    <small>{{ $state['presentationStatus'] === 'VotingOpen' ? 'Tiempo de Votación' : 'Tiempo de Exposición' }}</small>
                    @if($state['timerEndsAt'])
                        <strong data-countdown="{{ $state['timerEndsAt'] }}" data-timer-remaining="{{ $state['timerRemainingSeconds'] }}" data-timer-paused="{{ $state['timerIsPaused'] ? '1' : '0' }}" style="font-size: 38px;">{{ $timer }}</strong>
                    @else
                        <strong data-paused-timer data-timer-remaining="{{ $state['timerRemainingSeconds'] }}" data-timer-paused="1" style="font-size: 38px;">{{ $timer }}</strong>
                    @endif
                </div>
            </div>

            <!-- Botonera de Operaciones del Escenario -->
            <div class="control-actions" style="display: flex; flex-direction: column; gap: 12px;">
                @if(in_array($event->status, ['Draft', 'Scheduled']))
                    <form action="{{ route('admin.control', [$event, 'lobby']) }}" method="post">
                        @csrf
                        <button class="button button-secondary button-block" type="submit">
                            <span class="material-symbols-outlined">door_front</span> Abrir Lobby de Votación
                        </button>
                    </form>
                @endif

                @if(in_array($event->status, ['LobbyOpen', 'Scheduled']))
                    <form class="primary-operation" action="{{ route('admin.control', [$event, 'start']) }}" method="post">
                        @csrf
                        <button class="button button-block primary-operation" type="submit" style="font-size: 16px; padding: 14px;">
                            <span class="material-symbols-outlined">play_arrow</span> Iniciar Evento en Vivo
                        </button>
                    </form>
                @endif

                @if($state['presentationStatus'] === 'OnStage' && $state['presentationId'])
                    <form class="primary-operation" action="{{ route('admin.control', [$event, 'open']) }}" method="post">
                        @csrf
                        <input type="hidden" name="presentation_id" value="{{ $state['presentationId'] }}">
                        <button class="button button-accent button-block primary-operation" type="submit" style="font-size: 18px; padding: 16px; font-weight: 700; letter-spacing: 0.03em;">
                            <span class="material-symbols-outlined" style="font-size: 26px;">how_to_vote</span> ABRIR VOTACIÓN
                        </button>
                    </form>
                @endif

                @if($state['presentationStatus'] === 'VotingOpen' && $state['presentationId'])
                    <form class="primary-operation" action="{{ route('admin.control', [$event, 'close']) }}" method="post">
                        @csrf
                        <input type="hidden" name="presentation_id" value="{{ $state['presentationId'] }}">
                        <button class="button button-danger button-block primary-operation" data-confirm="Se cerrará la votación y no se recibirán nuevos votos para este equipo. ¿Continuar?" type="submit" style="font-size: 18px; padding: 16px; font-weight: 700;">
                            <span class="material-symbols-outlined" style="font-size: 26px;">stop_circle</span> CERRAR VOTACIÓN
                        </button>
                    </form>
                @endif

                @if($state['presentationStatus'] === 'VotingClosed' && $state['presentationId'])
                    <div style="background: var(--surface-subtle); padding: 14px; border-radius: 8px; text-align: center;">
                        <span class="badge badge-success" style="margin-bottom: 8px;">Votación Cerrada</span>
                        <p style="margin: 0 0 10px; font-size: 13px; color: var(--text-muted);">
                            Votos guardados en el registro. Puedes seleccionar al siguiente equipo en la lista.
                        </p>
                    </div>
                @endif
            </div>
        </article>

        @if($state['presentationStatus'] === 'VotingOpen')
            <div class="integrity-alert" style="margin-top: 16px;">
                <span class="material-symbols-outlined">verified_user</span>
                <span>Integridad criptográfica activa: firmas de voto, ponderación de jurado y verificación de sesión ejecutándose.</span>
            </div>
        @endif
    </section>

    <!-- Columna 3: Monitor de Participación y Jurados -->
    <aside>
        <h2 style="font-size: 16px; margin: 0 0 12px;">Monitor de Participación</h2>
        <div class="card monitor stack" style="gap: 18px; padding: 20px;">
            <div class="monitor-section">
                <div class="monitor-label">
                    <span><span class="material-symbols-outlined">gavel</span> Jurados que han votado</span>
                    <strong><span data-jury-count>{{ $state['jurorVoteCount'] }}</span> / {{ $state['jurorTotal'] }}</strong>
                </div>
                <div class="monitor-track">
                    <span data-jury-track style="width:{{ $state['jurorTotal'] ? $state['jurorVoteCount'] * 100 / $state['jurorTotal'] : 0 }}%"></span>
                </div>
            </div>

            <div class="monitor-section">
                <div class="monitor-label">
                    <span><span class="material-symbols-outlined">groups</span> Votos del Público</span>
                    <strong data-public-count>{{ $state['publicVoteCount'] }}</strong>
                </div>
                <div class="monitor-track accent">
                    <span data-public-track style="width:{{ min(100, $state['publicVoteCount'] * 5) }}%"></span>
                </div>
            </div>

            <!-- Estado nominal de cada Jurado -->
            @if($jurors->count())
                <div class="juror-live-status" style="border-top: 1px solid var(--border-subtle); padding-top: 16px; margin-top: 4px;">
                    <h3 style="font-size: 14px; color: var(--text); margin: 0 0 10px;">Estado de Evaluación del Jurado</h3>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @foreach($jurors as $juror)
                            <div data-juror-row="{{ $juror['id'] }}" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: var(--surface-subtle); border-radius: 6px; font-size: 13px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-outlined juror-status-icon" style="font-size: 18px; color: {{ $juror['hasVoted'] ? 'var(--success, #16a34a)' : 'var(--text-muted)' }};">
                                        {{ $juror['hasVoted'] ? 'check_circle' : 'pending' }}
                                    </span>
                                    <strong style="color: var(--text);">{{ $juror['name'] }}</strong>
                                </div>
                                @if($juror['hasVoted'])
                                    <span class="badge badge-sm badge-success juror-status-badge">Voto recibido</span>
                                @else
                                    <span class="badge badge-sm badge-muted juror-status-badge">Pendiente</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </aside>
</div>

<!-- Modal: Reiniciar Ronda (Flexible) -->
<div class="modal-backdrop" id="restart-round-modal" style="display: none;">
    <div class="modal-card" style="max-width: 540px;">
        <div class="modal-header">
            <h3>Gestión de Reinicio de Ronda</h3>
            <button type="button" class="icon-button" data-modal-close="restart-round-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('admin.control', [$event, 'restart']) }}" method="post">
            @csrf
            <div class="modal-body stack" style="gap: 16px;">
                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">
                    Selecciona cómo deseas gestionar el reinicio para el evento <strong>{{ $event->name }}</strong>:
                </p>

                <div class="card" style="padding: 14px; border: 1px solid var(--border-subtle); cursor: pointer;" onclick="document.getElementById('mode-same-round').checked = true;">
                    <label style="display: flex; gap: 12px; align-items: flex-start; cursor: pointer;">
                        <input type="radio" name="round_mode" id="mode-same-round" value="same_round" checked style="margin-top: 4px;">
                        <div>
                            <strong style="font-size: 15px; color: var(--text);">Reiniciar esta misma ronda (Ronda {{ $event->current_round }})</strong>
                            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">
                                Vuelve a poner a todos los equipos en <em>Pendiente</em> y limpia los votos de esta ronda para repetir las presentaciones desde cero. El número de ronda no cambiará.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="card" style="padding: 14px; border: 1px solid var(--border-subtle); cursor: pointer;" onclick="document.getElementById('mode-next-round').checked = true;">
                    <label style="display: flex; gap: 12px; align-items: flex-start; cursor: pointer;">
                        <input type="radio" name="round_mode" id="mode-next-round" value="next_round" style="margin-top: 4px;">
                        <div>
                            <strong style="font-size: 15px; color: var(--text);">Avanzar a la siguiente ronda (Ronda {{ $event->current_round + 1 }})</strong>
                            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">
                                Cierra y calcula los resultados finales de la ronda {{ $event->current_round }}, conservando todo el historial, y genera una nueva ronda {{ $event->current_round + 1 }} en estado borrador.
                            </p>
                        </div>
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="button button-ghost" data-modal-close="restart-round-modal">Cancelar</button>
                <button type="submit" class="button button-danger">
                    <span class="material-symbols-outlined">restart_alt</span> Confirmar y Reiniciar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
