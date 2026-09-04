@extends('layouts.admin')
@section('title', $event ? 'Editar evento' : 'Crear evento')

@php
    $jury = $event?->groups?->firstWhere('role_type', 'Jury');
    $public = $event?->groups?->firstWhere('role_type', 'Public');
    $tz = old('time_zone', $event?->time_zone ?? 'America/Santo_Domingo');
    $startsAtFormatted = old('starts_at_local', $event?->starts_at ? $event->starts_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i') : now()->setTimezone($tz)->addWeek()->setTime(9, 0)->format('Y-m-d\TH:i'));
    $endsAtFormatted = old('ends_at_local', $event?->ends_at ? $event->ends_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i') : now()->setTimezone($tz)->addWeek()->setTime(12, 0)->format('Y-m-d\TH:i'));

    $selectedTemplate = $selectedTemplate ?? null;
    $templates = $templates ?? collect();

    $defaultPresentation = $selectedTemplate ? $selectedTemplate->configValue('presentation_duration_seconds', 300) : 300;
    $defaultVoting = $selectedTemplate ? $selectedTemplate->configValue('voting_duration_seconds', 180) : 180;
    $defaultJury = $selectedTemplate ? $selectedTemplate->juryWeight() : 70;
    $defaultPublic = $selectedTemplate ? $selectedTemplate->publicWeight() : 30;
    $defaultCategory = $selectedTemplate ? $selectedTemplate->configValue('category', '') : '';
    $defaultDescription = $selectedTemplate ? $selectedTemplate->description : '';
    $defaultAccessMode = $selectedTemplate ? $selectedTemplate->configValue('public_access_mode', 'Device') : 'Device';
@endphp

@section('content')
<header class="page-heading">
    <div>
        <h1>{{ $event ? 'Editar Evento' : 'Crear Nuevo Evento' }}</h1>
        <p>Configura los detalles base para iniciar el desafío de innovación.</p>
    </div>
    @if($event)
        <div class="page-actions">
            <button type="button" class="button button-secondary" data-modal-open="save-template-modal">
                <span class="material-symbols-outlined">bookmark_add</span> Guardar como plantilla
            </button>
        </div>
    @endif
</header>

<div class="wizard">
    <nav class="wizard-steps" aria-label="Configuración del evento">
        <span class="wizard-step is-active" data-step="1">Identidad</span>
        @if($event)
            <a class="wizard-step" data-step="2" href="{{ route('admin.participants', $event) }}">Participantes</a>
            <a class="wizard-step" data-step="3" href="{{ route('admin.voting', $event) }}">Audiencias</a>
            <a class="wizard-step" data-step="4" href="{{ route('admin.voting', $event) }}#criterios">Criterios</a>
            <a class="wizard-step" data-step="5" href="{{ route('admin.voters', $event) }}">Accesos</a>
            <a class="wizard-step" data-step="6" href="{{ route('admin.live', $event) }}">Operación</a>
            <a class="wizard-step" data-step="7" href="{{ route('admin.event.results', $event) }}">Publicar</a>
        @else
            @foreach(['Participantes', 'Audiencias', 'Criterios', 'Accesos', 'Operación', 'Publicar'] as $i => $label)
                <span class="wizard-step" data-step="{{ $i + 2 }}">{{ $label }}</span>
            @endforeach
        @endif
    </nav>

    @if(!$event && isset($templates) && $templates->isNotEmpty())
        <div class="card" style="background: rgba(4, 46, 128, 0.04); border: 1px dashed var(--border); padding: 16px 20px; margin-bottom: 20px; border-radius: 12px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-symbols-outlined" style="color: var(--primary); font-size: 24px;">dashboard_customize</span>
                    <div>
                        <strong style="font-size: 14px; color: var(--text); display: block;">¿Deseas partir de una plantilla predefinida?</strong>
                        <span style="font-size: 12px; color: var(--text-muted);">Carga criterios, tiempos y ponderaciones automáticamente.</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <select id="template-selector" class="form-control" style="font-size: 13px; min-width: 250px;" onchange="applyTemplate(this.value)">
                        <option value="">-- Configuración en blanco (Manual) --</option>
                        @foreach($templates as $tmpl)
                            <option value="{{ $tmpl->id }}" @selected($selectedTemplate?->id === $tmpl->id)
                                data-name="{{ $tmpl->name }}"
                                data-desc="{{ $tmpl->description }}"
                                data-category="{{ $tmpl->configValue('category', 'Innovación') }}"
                                data-presentation="{{ $tmpl->configValue('presentation_duration_seconds', 300) }}"
                                data-voting="{{ $tmpl->configValue('voting_duration_seconds', 180) }}"
                                data-jury="{{ $tmpl->juryWeight() }}"
                                data-public="{{ $tmpl->publicWeight() }}"
                                data-access="{{ $tmpl->configValue('public_access_mode', 'Device') }}">
                                {{ $tmpl->name }} ({{ $tmpl->presentationMinutes() }}m / {{ $tmpl->votingMinutes() }}m)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endif

    <form class="card admin-form-card" action="{{ $event ? route('admin.events.update', $event) : route('admin.events.store') }}" method="post">
        @csrf
        @if(!$event)
            <input type="hidden" name="template_id" id="template_id_input" value="{{ old('template_id', $selectedTemplate?->id) }}">
        @endif

        @if($errors->any())
            <div class="validation-summary-errors">
                <ul>
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h2>Información General</h2>
        <div class="form-grid">
            <div class="form-group span-2">
                <label>Nombre del evento</label>
                <input class="form-control" name="name" value="{{ old('name', $event?->name) }}" required>
            </div>
            <div class="form-group">
                <label>Subtítulo</label>
                <input class="form-control" name="subtitle" value="{{ old('subtitle', $event?->subtitle) }}">
            </div>
            <div class="form-group">
                <label>Categoría</label>
                <input class="form-control" name="category" value="{{ old('category', $event?->category ?? $defaultCategory) }}" placeholder="Innovación">
            </div>
            <div class="form-group span-2">
                <label>Descripción</label>
                <textarea class="form-control" name="description">{{ old('description', $event?->description ?? $defaultDescription) }}</textarea>
            </div>
            <div class="form-group">
                <label>Organizador</label>
                <input class="form-control" name="organizer" value="{{ old('organizer', $event?->organizer) }}">
            </div>
            <div class="form-group">
                <label>Lugar</label>
                <input class="form-control" name="venue" value="{{ old('venue', $event?->venue) }}">
            </div>
            <div class="form-group">
                <label>Inicio</label>
                <input class="form-control" name="starts_at_local" type="datetime-local" value="{{ $startsAtFormatted }}">
            </div>
            <div class="form-group">
                <label>Final</label>
                <input class="form-control" name="ends_at_local" type="datetime-local" value="{{ $endsAtFormatted }}">
            </div>
            <div class="form-group">
                <label>Zona horaria</label>
                <input class="form-control" name="time_zone" value="{{ $tz }}">
            </div>
            <div class="form-group">
                <label>Código</label>
                <input class="form-control" name="code" value="{{ old('code', $event?->code) }}" placeholder="Automático" @readonly($event)>
            </div>
            <div class="form-group">
                <label>Acceso del público</label>
                <select class="form-control" name="public_access_mode">
                    @foreach(\App\Support\Domain::ACCESS_MODES as $mode)
                        <option value="{{ $mode }}" @selected(old('public_access_mode', $event?->public_access_mode ?? $defaultAccessMode) === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Visibilidad de resultados</label>
                <select class="form-control" name="results_visibility">
                    @foreach(\App\Support\Domain::RESULTS_VISIBILITIES as $mode)
                        <option value="{{ $mode }}" @selected(old('results_visibility', $event?->results_visibility ?? 'PublishedOnly') === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Presentación (segundos)</label>
                <input class="form-control" name="presentation_duration_seconds" type="number" value="{{ old('presentation_duration_seconds', $event?->presentation_duration_seconds ?? $defaultPresentation) }}">
            </div>
            <div class="form-group">
                <label>Votación (segundos)</label>
                <input class="form-control" name="voting_duration_seconds" type="number" value="{{ old('voting_duration_seconds', $event?->voting_duration_seconds ?? $defaultVoting) }}">
            </div>
            @if(!$event)
                <div class="form-group">
                    <label>Peso jurado (%)</label>
                    <input class="form-control" name="jury_weight_percent" type="number" step=".01" value="{{ old('jury_weight_percent', $defaultJury) }}">
                </div>
                <div class="form-group">
                    <label>Peso público (%)</label>
                    <input class="form-control" name="public_weight_percent" type="number" step=".01" value="{{ old('public_weight_percent', $defaultPublic) }}">
                </div>
            @endif
            <label class="check-row span-2">
                <input type="checkbox" name="allow_juror_vote_edit" value="1" @checked(old('allow_juror_vote_edit', $event?->allow_juror_vote_edit ?? true))>
                <span>Permitir al jurado editar su evaluación mientras la votación esté abierta</span>
            </label>
        </div>
        <div class="page-actions" style="margin-top:1.2rem">
            <a class="button button-secondary" href="{{ route('admin.index') }}">Cancelar</a>
            <button class="button" type="submit">
                <span class="material-symbols-outlined">save</span> {{ $event ? 'Guardar identidad' : 'Guardar y configurar participantes' }}
            </button>
        </div>
    </form>

    <aside class="card event-preview">
        <div class="preview-cover"><span class="material-symbols-outlined">event</span></div>
        <div class="preview-body">
            <span class="badge badge-primary">Vista pública</span>
            <h3>{{ old('name', $event?->name) ?: 'Nombre del evento' }}</h3>
            <p>{{ old('subtitle', $event?->subtitle) ?: 'Subtítulo o mensaje institucional' }}</p>
            <strong>{{ $event?->starts_at ? $event->starts_at->copy()->setTimezone($tz)->format('d M Y, h:i A') : 'Fecha por definir' }}</strong>
        </div>
        <div class="preview-footer">Esta es una vista previa de cómo se verá tu evento.</div>
    </aside>
</div>

@if($event)
<!-- Modal: Guardar como Plantilla -->
<div class="modal-backdrop" id="save-template-modal" style="display: none;">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="color: var(--primary);">bookmark_add</span>
                <h3>Guardar evento como plantilla</h3>
            </div>
            <button type="button" class="icon-button" data-modal-close="save-template-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('admin.events.save-template', $event) }}" method="post">
            @csrf
            <div class="modal-body stack" style="gap: 16px;">
                <p style="font-size: 13px; color: var(--text-muted); margin: 0;">
                    Se guardarán los tiempos de exposición y votación, ponderaciones y criterios de evaluación actuales de <strong>{{ $event->name }}</strong> como una plantilla reutilizable.
                </p>
                <div class="form-group">
                    <label for="template-name-input">Nombre de la nueva plantilla</label>
                    <input id="template-name-input" class="form-control" type="text" name="template_name" value="Plantilla - {{ $event->name }}" required maxlength="180">
                </div>
                <div class="form-group">
                    <label for="template-desc-input">Descripción opcional</label>
                    <textarea id="template-desc-input" class="form-control" name="template_description" rows="2" placeholder="Plantilla basada en la configuración de {{ $event->name }}."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="button button-ghost" data-modal-close="save-template-modal">Cancelar</button>
                <button type="submit" class="button button-accent">
                    <span class="material-symbols-outlined">save</span> Guardar plantilla
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function applyTemplate(templateId) {
    const select = document.getElementById('template-selector');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    const tmplIdInput = document.getElementById('template_id_input');
    if (tmplIdInput) tmplIdInput.value = templateId;
    if (!templateId || !opt) return;

    if (opt.dataset.category) {
        const catInput = document.querySelector('input[name="category"]');
        if (catInput) catInput.value = opt.dataset.category;
    }
    if (opt.dataset.desc) {
        const descInput = document.querySelector('textarea[name="description"]');
        if (descInput && !descInput.value) descInput.value = opt.dataset.desc;
    }
    if (opt.dataset.presentation) {
        const presInput = document.querySelector('input[name="presentation_duration_seconds"]');
        if (presInput) presInput.value = opt.dataset.presentation;
    }
    if (opt.dataset.voting) {
        const voteInput = document.querySelector('input[name="voting_duration_seconds"]');
        if (voteInput) voteInput.value = opt.dataset.voting;
    }
    if (opt.dataset.jury) {
        const juryInput = document.querySelector('input[name="jury_weight_percent"]');
        if (juryInput) juryInput.value = opt.dataset.jury;
    }
    if (opt.dataset.public) {
        const pubInput = document.querySelector('input[name="public_weight_percent"]');
        if (pubInput) pubInput.value = opt.dataset.public;
    }
    if (opt.dataset.access) {
        const accessSelect = document.querySelector('select[name="public_access_mode"]');
        if (accessSelect) accessSelect.value = opt.dataset.access;
    }
}
</script>
@endsection

