@extends('layouts.admin')

@section('title', 'Editar equipo')

@section('content')
<header class="page-heading">
    <div>
        <span class="eyebrow">Equipo #{{ $participant->presentation_order }}</span>
        <h1>Editar equipo</h1>
        <p>Actualiza la identidad del equipo y la información que verá el público.</p>
    </div>
    <a class="button button-secondary" href="{{ route('admin.participants', $event) }}">
        <span class="material-symbols-outlined">arrow_back</span> Volver a participantes
    </a>
</header>

<div class="participant-editor-layout">
    <section class="card panel participant-editor-card">
        <header class="panel-header">
            <div>
                <h2>Información del equipo</h2>
                <p class="form-hint">Los cambios se reflejan en las pantallas públicas y en los resultados.</p>
            </div>
            <span class="badge {{ $participant->status === 'Active' ? 'badge-success' : 'badge-muted' }}">
                {{ $participant->status === 'Active' ? 'Habilitado' : 'Inhabilitado' }}
            </span>
        </header>

        @if($errors->any())
            <div class="validation-summary-errors" role="alert">
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('admin.participants.update', $participant) }}" method="post">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label for="participant-name">Nombre del equipo</label>
                    <input class="form-control" id="participant-name" name="name" value="{{ old('name', $participant->name) }}" maxlength="180" required>
                </div>
                <div class="form-group">
                    <label for="participant-project">Título del proyecto</label>
                    <input class="form-control" id="participant-project" name="project_title" value="{{ old('project_title', $participant->project_title) }}" maxlength="240">
                </div>
                <x-member-list-input id="participant-members" :members="old('members', $participant->member_names)" />
                <div class="form-group">
                    <label for="participant-area">Área</label>
                    <input class="form-control" id="participant-area" name="area" value="{{ old('area', $participant->area) }}" maxlength="160">
                </div>
                <div class="form-group span-2">
                    <label for="participant-description">Descripción</label>
                    <textarea class="form-control" id="participant-description" name="description" maxlength="3000">{{ old('description', $participant->description) }}</textarea>
                </div>
            </div>
            <div class="participant-editor-actions">
                <a class="button button-secondary" href="{{ route('admin.participants', $event) }}">Cancelar</a>
                <button class="button" type="submit"><span class="material-symbols-outlined">save</span> Guardar cambios</button>
            </div>
        </form>
    </section>

    <aside class="card participant-edit-preview">
        <span class="eyebrow">Vista previa pública</span>
        <div class="participant-preview-number">{{ str_pad((string) $participant->presentation_order, 2, '0', STR_PAD_LEFT) }}</div>
        <h2>{{ $participant->name }}</h2>
        <p class="participant-preview-project">{{ $participant->project_title ?: 'Título del proyecto' }}</p>
        <div class="participant-preview-meta">
            <span><span class="material-symbols-outlined">category</span>{{ $participant->area ?: 'Área sin definir' }}</span>
            <span><span class="material-symbols-outlined">groups</span>{{ $participant->member_names ? count($participant->member_names).' integrantes' : 'Integrantes sin definir' }}</span>
        </div>
        @if($participant->member_names)
            <div class="member-chip-list member-chip-list-dark" aria-label="Integrantes del equipo">
                @foreach($participant->member_names as $member)<span>{{ $member }}</span>@endforeach
            </div>
        @endif
        <p>{{ $participant->description ?: 'Agrega una descripción para presentar mejor este proyecto.' }}</p>
    </aside>
</div>
@endsection
