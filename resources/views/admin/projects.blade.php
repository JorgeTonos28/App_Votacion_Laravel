@extends('layouts.admin')
@section('title', 'Directorio de Proyectos y Competiciones')

@section('content')
<header class="page-heading">
    <div>
        <h1>Directorio de Proyectos y Competiciones</h1>
        <p>Catálogo centralizado de eventos para administrar equipos, criterios, jurados y configuraciones avanzadas.</p>
    </div>
    <div class="dashboard-heading-actions">
        <label class="dashboard-search">
            <span class="material-symbols-outlined">search</span>
            <input type="search" placeholder="Filtrar por código o nombre..." data-event-search>
        </label>
        @if(auth()->user()->isAdministrator())
            <a class="button button-accent" href="{{ route('admin.events.create') }}">
                <span class="material-symbols-outlined">add</span> Crear Nuevo Proyecto
            </a>
        @endif
    </div>
</header>

<section class="card panel" id="event-directory" data-event-directory>
    <header class="panel-header">
        <div>
            <h2>Todos los Proyectos</h2>
            <p class="form-hint" style="margin: 0;">Selecciona un proyecto para gestionar sus participantes, rúbricas y jurados.</p>
        </div>
        <div class="directory-filters">
            <select class="form-control" data-event-status aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                @foreach(\App\Support\Domain::EVENT_STATUSES as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
            <span class="form-hint" data-event-count>{{ $events->count() }} eventos</span>
        </div>
    </header>

    @if($events->isEmpty())
        <div class="empty-state">
            <span class="material-symbols-outlined">inventory_2</span>
            <h3>No hay proyectos registrados</h3>
            <p>Crea el primero para configurar sus participantes, jurados y rúbricas de evaluación.</p>
            @if(auth()->user()->isAdministrator())
                <a class="button button-accent" href="{{ route('admin.events.create') }}" style="margin-top: 14px;">
                    <span class="material-symbols-outlined">add</span> Crear Proyecto Ahora
                </a>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Proyecto / Competencia</th>
                        <th>Código</th>
                        <th>Acceso Público</th>
                        <th>Ronda</th>
                        <th>Equipos</th>
                        <th>Jurados</th>
                        <th>Votos</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Módulos de Gestión</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $item)
                        @php
                            $badge = in_array($item->status, ['Live', 'LobbyOpen']) ? 'badge-live' : (in_array($item->status, ['Finished', 'Published']) ? 'badge-primary' : 'badge-muted');
                        @endphp
                        <tr data-event-row data-name="{{ strtolower($item->name) }}" data-code="{{ strtolower($item->code) }}" data-status="{{ $item->status }}">
                            <td>
                                <a class="table-title" href="{{ route('admin.events.edit', $item) }}" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                    {{ $item->name }}
                                </a>
                                <div class="table-subtitle">
                                    {{ $item->venue ?: 'Sin sede' }} · {{ $item->organizer ?: 'Organizador no especificado' }}
                                </div>
                            </td>
                            <td><code>{{ $item->code }}</code></td>
                            <td>
                                <span class="badge badge-sm badge-subtle">
                                    {{ match($item->public_access_mode) {
                                        'Open' => 'Acceso Libre',
                                        'DeviceFingerprint' => '1 Voto por Equipo',
                                        'IndividualCode' => 'Código Único',
                                        default => $item->public_access_mode
                                    } }}
                                </span>
                            </td>
                            <td><span class="badge badge-subtle">Ronda {{ $item->current_round }}</span></td>
                            <td><strong>{{ $item->participants_count }}</strong></td>
                            <td><strong>{{ $item->jurors_count }}</strong></td>
                            <td><strong>{{ $item->votes_count }}</strong></td>
                            <td><span class="badge {{ $badge }}">{{ $item->status }}</span></td>
                            <td>
                                <div class="table-actions" style="justify-content: flex-end; gap: 4px;">
                                    <a class="icon-button" href="{{ route('admin.live', $item) }}" title="Mesa de Control en Vivo">
                                        <span class="material-symbols-outlined" style="color: var(--primary);">live_tv</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.participants', $item) }}" title="Equipos Participantes">
                                        <span class="material-symbols-outlined">inventory_2</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.jurors', $item) }}" title="Jurados Asignados">
                                        <span class="material-symbols-outlined">groups</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.voting', $item) }}" title="Criterios y Rúbricas">
                                        <span class="material-symbols-outlined">tune</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.event.results', $item) }}" title="Resultados y Ranking">
                                        <span class="material-symbols-outlined">emoji_events</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.reports', $item) }}" title="Reportes y Auditoría">
                                        <span class="material-symbols-outlined">summarize</span>
                                    </a>
                                    <a class="icon-button" href="{{ route('admin.events.edit', $item) }}" title="Configuración del Evento">
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
