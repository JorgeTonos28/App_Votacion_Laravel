@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<header class="page-heading">
    <div>
        <h1>Centro de Control · Eventos Activos</h1>
        <p>Supervisión en tiempo real de competencias activas, participación de la audiencia y evaluación del jurado.</p>
    </div>
    <div class="dashboard-heading-actions">
        <label class="dashboard-search">
            <span class="material-symbols-outlined">search</span>
            <input type="search" placeholder="Buscar evento o sala..." data-event-search>
        </label>
        @if(auth()->user()->isAdministrator())
            <a class="button button-accent" href="{{ route('admin.events.create') }}">
                <span class="material-symbols-outlined">add</span> Nuevo Evento
            </a>
        @endif
    </div>
</header>

<!-- KPIs en tiempo real -->
<section class="metric-grid" data-live-metrics-url="{{ route('admin.metrics.live') }}">
    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Eventos en Vivo / Lobby</span>
            <span class="metric-icon success"><span class="material-symbols-outlined">live_tv</span></span>
        </div>
        <div class="metric-value" data-metric="activeEvents">{{ $metrics['activeEvents'] }}</div>
        <div class="metric-note">
            @if($metrics['activeEvents'] > 0)
                <span class="pulse-indicator" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#22c55e; margin-right:4px;"></span>
                Transmisión y votación en marcha
            @else
                Sin salas activas en este momento
            @endif
        </div>
    </article>

    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Dispositivos Conectados</span>
            <span class="metric-icon success"><span class="material-symbols-outlined">sensors</span></span>
        </div>
        <div class="metric-value" data-metric="connectedUsers" style="color: var(--success, #16a34a);">{{ $metrics['connectedUsers'] }}</div>
        <div class="metric-note">Actividad en los últimos 3 min</div>
    </article>

    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Votos Registrados</span>
            <span class="metric-icon accent"><span class="material-symbols-outlined">how_to_vote</span></span>
        </div>
        <div class="metric-value" data-metric="totalVotes">{{ number_format($metrics['totalVotes']) }}</div>
        <div class="metric-note">{{ $metrics['totalParticipants'] }} equipos participantes</div>
    </article>

    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Jurados Habilitados</span>
            <span class="metric-icon"><span class="material-symbols-outlined">gavel</span></span>
        </div>
        <div class="metric-value" data-metric="activeJurors">{{ $metrics['activeJurors'] }}</div>
        <div class="metric-note">Con credencial activa</div>
    </article>
</section>

<!-- Eventos en Escenario Ahora -->
@php
    $liveEvents = $events->whereIn('status', ['Live', 'LobbyOpen', 'Paused'])->values();
@endphp

@if($liveEvents->isNotEmpty())
    <section class="card panel" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
        <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="pulse-indicator" style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#22c55e;"></span>
                <h2>Salas y Escenarios en Curso</h2>
            </div>
            <span class="badge badge-live">{{ $liveEvents->count() }} activa(s)</span>
        </header>
        <div class="panel-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            @foreach($liveEvents as $liveEvent)
                <div class="card" style="padding: 16px; background: var(--surface-subtle); border: 1px solid var(--border-subtle); display: flex; flex-direction: column; justify-content: space-between; gap: 14px;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="badge {{ $liveEvent->status === 'Live' ? 'badge-live' : ($liveEvent->status === 'LobbyOpen' ? 'badge-primary' : 'badge-warning') }}">
                                {{ $liveEvent->status }}
                            </span>
                            <span class="badge badge-subtle">Ronda {{ $liveEvent->current_round }}</span>
                        </div>
                        <strong style="font-size: 16px; color: var(--text); display: block;">{{ $liveEvent->name }}</strong>
                        <div class="table-subtitle" style="margin-top: 4px;">Código de acceso: <code>{{ $liveEvent->code }}</code> · {{ $liveEvent->venue ?: 'Sin recinto' }}</div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a class="button button-sm button-accent" href="{{ route('admin.live', $liveEvent) }}">
                            <span class="material-symbols-outlined">live_tv</span> Mesa de Control
                        </a>
                        <a class="button button-sm button-outline" target="_blank" href="{{ route('projection.live', $liveEvent->code) }}">
                            <span class="material-symbols-outlined">open_in_new</span> Proyección
                        </a>
                        <a class="button button-sm button-ghost" href="{{ route('admin.event.results', $liveEvent) }}">
                            <span class="material-symbols-outlined">leaderboard</span> Resultados
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

<!-- Resumen Operativo de Eventos -->
<section class="card panel" id="event-directory" data-event-directory>
    <header class="panel-header">
        <div>
            <h2>Resumen General de Competencias</h2>
            <p class="form-hint" style="margin: 0;">Vista ejecutiva de todos los eventos del sistema.</p>
        </div>
        <div class="directory-filters">
            <select class="form-control" data-event-status aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                @foreach(\App\Support\Domain::EVENT_STATUSES as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
            <a class="button button-sm button-ghost" href="{{ route('admin.projects') }}" title="Ir al directorio completo de proyectos">
                <span class="material-symbols-outlined">inventory_2</span> Gestión Detallada
            </a>
        </div>
    </header>

    @if($events->isEmpty())
        <div class="empty-state">
            <span class="material-symbols-outlined">event_busy</span>
            <h3>No hay eventos registrados</h3>
            <p>Crea tu primera competencia para comenzar a recibir votos y evaluaciones.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Ronda</th>
                        <th>Equipos</th>
                        <th>Votos</th>
                        <th style="text-align: right;">Acceso Rápido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $item)
                        @php
                            $badge = in_array($item->status, ['Live', 'LobbyOpen']) ? 'badge-live' : (in_array($item->status, ['Finished', 'Published']) ? 'badge-primary' : 'badge-muted');
                        @endphp
                        <tr data-event-row data-name="{{ strtolower($item->name) }}" data-code="{{ strtolower($item->code) }}" data-status="{{ $item->status }}">
                            <td>
                                <strong class="table-title">{{ $item->name }}</strong>
                                <div class="table-subtitle">{{ $item->venue ?: 'Sin lugar definido' }} · {{ $item->organizer ?: 'InnovaMente' }}</div>
                            </td>
                            <td><code>{{ $item->code }}</code></td>
                            <td><span class="badge {{ $badge }}">{{ $item->status }}</span></td>
                            <td><span class="badge badge-subtle">R{{ $item->current_round }}</span></td>
                            <td><strong>{{ $item->participants_count }}</strong></td>
                            <td><strong>{{ $item->votes_count }}</strong></td>
                            <td>
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a class="button button-sm button-outline" href="{{ route('admin.live', $item) }}" title="Mesa de Control en Vivo">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">live_tv</span> Control
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.events.edit', $item) }}" title="Editar configuración">
                                        <span class="material-symbols-outlined">settings</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
