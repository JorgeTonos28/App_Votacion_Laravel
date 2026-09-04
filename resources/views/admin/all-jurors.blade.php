@extends('layouts.admin')
@section('title', 'Jurados')

@section('content')
<header class="page-heading">
    <div>
        <h1>Directorio Global de Jurados</h1>
        <p>Base unificada de evaluadores con su trayectoria y asignaciones en las distintas competencias.</p>
    </div>
    <div class="dashboard-heading-actions">
        <label class="dashboard-search">
            <span class="material-symbols-outlined">search</span>
            <input type="search" placeholder="Buscar jurado por nombre o correo..." data-juror-search>
        </label>
        @if(auth()->user()->isAdministrator() && $events->count() > 0)
            <button type="button" class="button" data-modal-open="global-juror-modal">
                <span class="material-symbols-outlined">person_add</span> Nuevo Jurado
            </button>
        @endif
    </div>
</header>

@if(session('issuedJuror'))
    @php $issued = session('issuedJuror'); @endphp
    <div class="card panel credential-card" style="margin-bottom: 24px; border-left: 4px solid var(--accent, #fea203);">
        <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: var(--accent); font-size: 28px;">badge</span>
                <div>
                    <h2>Credencial de Acceso Generada</h2>
                    <p class="form-hint" style="margin: 0;">Comparte este código o enlace con el jurado.</p>
                </div>
            </div>
            <span class="badge badge-live">Listo para evaluar</span>
        </header>
        <div class="panel-body credential-body" style="display: grid; grid-template-columns: auto 1fr; gap: 24px; align-items: center;">
            <div class="credential-qr" style="text-align: center;">
                <img src="{{ $issued['qrDataUri'] }}" alt="Código QR de acceso" style="width: 140px; height: 140px; border-radius: 8px; border: 1px solid var(--border-subtle); background: #fff; padding: 6px;">
            </div>
            <div class="stack" style="gap: 12px;">
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <small style="color: var(--text-muted); display: block;">Jurado</small>
                        <strong style="font-size: 16px;">{{ $issued['name'] }}</strong>
                    </div>
                    <div>
                        <small style="color: var(--text-muted); display: block;">Evento Asignado</small>
                        <strong>{{ $issued['eventName'] }} (<code>{{ $issued['eventCode'] }}</code>)</strong>
                    </div>
                    <div>
                        <small style="color: var(--text-muted); display: block;">Código PIN Personal</small>
                        <strong style="font-size: 20px; color: var(--accent); font-family: monospace; letter-spacing: 2px;">{{ $issued['code'] }}</strong>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" class="form-control" value="{{ $issued['accessUrl'] }}" id="issued-access-url" readonly style="font-size: 12px; font-family: monospace;">
                    <button type="button" class="button button-sm button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('issued-access-url').value); alert('Enlace de jurado copiado al portapapeles.');">
                        <span class="material-symbols-outlined">content_copy</span> Copiar enlace
                    </button>
                    <button type="button" class="button button-sm button-outline" onclick="navigator.clipboard.writeText('{{ $issued['code'] }}'); alert('Código PIN copiado.');">
                        <span class="material-symbols-outlined">pin</span> Copiar PIN
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<section class="card panel">
    <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Directorio de Jurados</h2>
        <span class="form-hint" data-jurors-count>{{ $jurors->count() }} jurados registrados</span>
    </header>

    <div class="table-wrap">
        <table class="data-table" data-jurors-table>
            <thead>
                <tr>
                    <th>Jurado</th>
                    <th>Historial de eventos</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Evaluaciones</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jurors as $juror)
                    @php
                        $historyJson = $juror->history->map(function ($h) {
                            return [
                                'eventName' => $h->event->name,
                                'eventCode' => $h->event->code,
                                'eventStatus' => $h->event->status,
                                'jurorStatus' => $h->status,
                                'weight' => number_format($h->individual_weight, 2),
                                'createdAt' => $h->created_at?->format('d/m/Y') ?? 'N/D',
                                'eventUrl' => route('admin.jurors', $h->event_id),
                            ];
                        })->values()->toJson();
                    @endphp
                    <tr data-juror-row data-name="{{ strtolower($juror->name) }}" data-email="{{ strtolower($juror->email ?? '') }}" data-title="{{ strtolower($juror->title ?? '') }}">
                        <td>
                            <strong class="table-title">{{ $juror->name }}</strong>
                            <div class="table-subtitle">{{ $juror->title ?: 'Sin cargo registrado' }} · {{ $juror->email ?: 'Sin correo' }}</div>
                        </td>
                        <td>
                            <button type="button" class="button button-sm button-outline" style="padding: 4px 10px; font-size: 13px;" data-view-history='{{ $historyJson }}'>
                                <span class="material-symbols-outlined" style="font-size: 16px;">history</span>
                                <span>Ver historial ({{ $juror->events_count }} evento(s))</span>
                            </button>
                        </td>
                        <td>
                            <span class="badge {{ $juror->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $juror->is_active ? 'Activo' : 'Sin acceso activo' }}
                            </span>
                            <div class="table-subtitle">{{ $juror->events_count }} evento(s)</div>
                        </td>
                        <td>
                            <span class="table-subtitle">
                                {{ $juror->latest_access_at ? $juror->latest_access_at->setTimezone('America/Santo_Domingo')->format('d/m/Y H:i') : 'Sin accesos registrados' }}
                            </span>
                        </td>
                        <td>
                            <strong style="font-size: 14px;">{{ $juror->votes_count }}</strong>
                        </td>
                        <td>
                            <div class="table-actions" style="justify-content: flex-end;">
                                <button type="button" class="icon-button" title="Ver historial completo de eventos" data-view-history='{{ $historyJson }}'>
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                                <a class="icon-button" href="{{ route('admin.jurors.edit', $juror) }}?global=1" title="Editar información del jurado">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <span class="material-symbols-outlined">groups</span>
                                <h3>No hay jurados registrados todavía</h3>
                                <p>Crea un jurado o asígnalo a una competencia para comenzar a evaluar.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<!-- Modal: Historial de Participación -->
<div class="modal-backdrop" id="juror-history-modal" style="display: none;">
    <div class="modal-card" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <h3 id="history-modal-title">Historial de Participación</h3>
                <p class="form-hint" style="margin: 0;">Eventos y asignaciones registradas para este jurado.</p>
            </div>
            <button type="button" class="icon-button" data-modal-close="juror-history-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Código</th>
                            <th>Peso</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th style="text-align: right;">Acceso</th>
                        </tr>
                    </thead>
                    <tbody id="history-modal-body">
                        <!-- Llenado dinámicamente con JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; display: flex; justify-content: flex-end;">
            <button type="button" class="button button-ghost" data-modal-close="juror-history-modal">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: Registrar Nuevo Jurado Global -->
<div class="modal-backdrop" id="global-juror-modal" style="display: none;">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Registrar Nuevo Jurado</h3>
            <button type="button" class="icon-button" data-modal-close="global-juror-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('admin.jurors.global.add') }}" method="post">
            @csrf
            <div class="modal-body stack" style="gap: 16px;">
                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">
                    El jurado se vinculará a la competencia seleccionada y se le generará un código PIN de acceso automático.
                </p>

                <div class="form-group">
                    <label for="new-juror-event">Asignar al evento / competencia</label>
                    <select id="new-juror-event" class="form-control" name="event_id" required>
                        <option value="">-- Selecciona un evento --</option>
                        @foreach($events as $e)
                            <option value="{{ $e->id }}">
                                {{ $e->name }} ({{ $e->code }}) · {{ $e->status }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="new-juror-name">Nombre y apellido del jurado</label>
                    <input id="new-juror-name" class="form-control" type="text" name="name" required maxlength="180" placeholder="Ej. Dr. Roberto Gómez">
                </div>

                <div class="form-group">
                    <label for="new-juror-title">Cargo / Institución / Especialidad</label>
                    <input id="new-juror-title" class="form-control" type="text" name="title" maxlength="180" placeholder="Ej. Director de Innovación · PUCMM">
                </div>

                <div class="form-group">
                    <label for="new-juror-email">Correo electrónico (opcional)</label>
                    <input id="new-juror-email" class="form-control" type="email" name="email" maxlength="254" placeholder="jurado@ejemplo.com">
                    <small class="form-hint">Si proporcionas un correo, el sistema le enviará su enlace y PIN automáticamente.</small>
                </div>

                <div class="form-group">
                    <label for="new-juror-weight">Ponderación / Peso individual</label>
                    <input id="new-juror-weight" class="form-control" type="number" step="0.1" min="0.1" max="10" name="individual_weight" value="1.0" required>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="button button-ghost" data-modal-close="global-juror-modal">Cancelar</button>
                <button type="submit" class="button button-accent">
                    <span class="material-symbols-outlined">check_circle</span> Registrar y Generar Acceso
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
