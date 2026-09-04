@extends('layouts.admin')

@section('title', 'Participantes')

@php $canManage = auth()->user()->isAdministrator(); @endphp

@section('content')
<header class="page-heading">
    <div>
        <h1>Equipos y Proyectos</h1>
        <p>Edita la información, habilita o inhabilita equipos y elimina registros sin votos.</p>
    </div>
    @if($canManage)
        <div class="page-actions">
            <button class="button button-secondary" type="button" onclick="document.getElementById('csv-dialog').showModal()">
                <span class="material-symbols-outlined">upload_file</span> Importar CSV
            </button>
            <button class="button" type="button" onclick="document.getElementById('participant-dialog').showModal()">
                <span class="material-symbols-outlined">add</span> Añadir equipo
            </button>
        </div>
    @endif
</header>

@if($errors->any())
    <div class="validation-summary-errors" role="alert">
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="split-admin participants-layout">
    <section class="card panel">
        <header class="panel-header">
            <div>
                <h2>{{ $event->name }}</h2>
                <p class="form-hint">Los equipos inhabilitados no pasan al escenario ni aparecen en el ranking.</p>
            </div>
            <span class="badge badge-primary">{{ $participants->count() }} equipos</span>
        </header>

        @if($participants->isEmpty())
            <div class="empty-state">
                <span class="material-symbols-outlined">group_add</span>
                <h3>Agrega el primer equipo</h3>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table participants-table">
                    <thead><tr><th>Orden</th><th>Equipo</th><th>Proyecto</th><th>Estado</th>@if($canManage)<th class="align-right">Acciones</th>@endif</tr></thead>
                    <tbody>
                    @foreach($participants as $p)
                        <tr class="{{ $p->status === 'Disqualified' ? 'participant-disabled' : '' }}">
                            <td><span class="participant-order">{{ str_pad((string) $p->presentation_order, 2, '0', STR_PAD_LEFT) }}</span></td>
                            <td>
                                <div class="table-title">{{ $p->name }}</div>
                                @if($p->member_names)
                                    @php
                                        $memberCount = count($p->member_names);
                                        $remainingMembers = max(0, $memberCount - 3);
                                    @endphp
                                    <div class="table-subtitle">{{ $memberCount }} integrantes · {{ implode(', ', array_slice($p->member_names, 0, 3)) }}{{ $remainingMembers ? " y {$remainingMembers} más" : '' }}</div>
                                @else
                                    <div class="table-subtitle">Integrantes no especificados</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $p->project_title ?: 'Sin título' }}</div>
                                <div class="table-subtitle">{{ $p->area ?: 'Área no especificada' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $p->status === 'Active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $p->status === 'Active' ? 'Habilitado' : 'Inhabilitado' }}
                                </span>
                                @if($p->disqualification_reason)<small class="participant-reason">{{ $p->disqualification_reason }}</small>@endif
                            </td>
                            @if($canManage)
                                <td>
                                    <div class="participant-actions">
                                        <a class="icon-button" href="{{ route('admin.participants.edit', $p) }}" title="Editar {{ $p->name }}" aria-label="Editar {{ $p->name }}">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        @if($p->status === 'Active')
                                            <button class="icon-button participant-disable-button" type="button" title="Inhabilitar {{ $p->name }}" aria-label="Inhabilitar {{ $p->name }}" onclick="document.getElementById('disable-{{ $p->id }}').showModal()">
                                                <span class="material-symbols-outlined">block</span>
                                            </button>
                                        @else
                                            <form action="{{ route('admin.participants.status', $p) }}" method="post">
                                                @csrf
                                                <input type="hidden" name="status" value="Active">
                                                <button class="icon-button participant-enable-button" type="submit" title="Habilitar {{ $p->name }}" aria-label="Habilitar {{ $p->name }}">
                                                    <span class="material-symbols-outlined">check_circle</span>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('admin.participants.delete', $p) }}" method="post">
                                            @csrf
                                            <button class="icon-button participant-delete-button" type="submit" title="Eliminar {{ $p->name }}" aria-label="Eliminar {{ $p->name }}" data-confirm="Solo se puede eliminar un equipo sin votos. ¿Eliminar {{ $p->name }} definitivamente?">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <aside class="participants-side-stack">
        <article class="card preview-public">
            <span class="eyebrow">Previsualización pública</span>
            @php $selected = $participants->firstWhere('status', 'Active') ?? $participants->first(); @endphp
            <div class="participant-preview-number">{{ $selected ? str_pad((string) $selected->presentation_order, 2, '0', STR_PAD_LEFT) : '—' }}</div>
            <h2>{{ $selected?->name ?? 'Sin participante' }}</h2>
            <div class="project-box">
                <strong>{{ $selected?->project_title ?? 'Proyecto' }}</strong>
                <p>{{ $selected?->description ?? 'Agrega la descripción del proyecto.' }}</p>
            </div>
            @if($selected?->member_names)
                <div class="member-chip-list" aria-label="Integrantes del equipo">
                    @foreach($selected->member_names as $member)<span>{{ $member }}</span>@endforeach
                </div>
            @endif
        </article>
        <article class="card participant-rules-card">
            <span class="material-symbols-outlined">shield</span>
            <div><h3>Control con integridad</h3><p>Editar conserva votos. Inhabilitar excluye al equipo del ranking. Eliminar sólo está disponible si todavía no ha recibido votos.</p></div>
        </article>
    </aside>
</div>

@if($canManage)
    <dialog class="vote-dialog" id="participant-dialog">
        <form action="{{ route('admin.participants.add') }}" method="post">
            @csrf
            <input name="event_id" value="{{ $event->id }}" type="hidden">
            <h2>Agregar participante</h2>
            <div class="form-group"><label for="new-name">Nombre del equipo</label><input class="form-control" id="new-name" name="name" required maxlength="180"></div>
            <div class="form-group"><label for="new-project">Título del proyecto</label><input class="form-control" id="new-project" name="project_title" maxlength="240"></div>
            <x-member-list-input id="new-members" :members="old('members', [])" />
            <div class="form-group"><label for="new-area">Área</label><input class="form-control" id="new-area" name="area" maxlength="160"></div>
            <div class="form-group"><label for="new-description">Descripción</label><textarea class="form-control" id="new-description" name="description" maxlength="3000"></textarea></div>
            <div class="dialog-actions"><button class="button button-secondary" type="button" onclick="this.closest('dialog').close()">Cancelar</button><button class="button" type="submit">Agregar</button></div>
        </form>
    </dialog>

    <dialog class="vote-dialog" id="csv-dialog">
        <form action="{{ route('admin.participants.import', $event) }}" method="post" enctype="multipart/form-data">
            @csrf
            <h2>Importar participantes</h2>
            <x-csv-hint :columns="['nombre', 'proyecto', 'integrantes', 'area', 'descripcion']" :required="['nombre']" note="Usa la primera fila como encabezado. Separa los integrantes con punto y coma dentro de su columna." />
            <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
            <div class="dialog-actions"><button class="button button-secondary" type="button" onclick="this.closest('dialog').close()">Cancelar</button><button class="button" type="submit">Importar</button></div>
        </form>
    </dialog>

    @foreach($participants->where('status', 'Active') as $p)
        <dialog class="vote-dialog" id="disable-{{ $p->id }}">
            <form action="{{ route('admin.participants.status', $p) }}" method="post">
                @csrf
                <input type="hidden" name="status" value="Disqualified">
                <span class="dialog-icon dialog-icon-warning"><span class="material-symbols-outlined">block</span></span>
                <h2>Inhabilitar equipo</h2>
                <p><strong>{{ $p->name }}</strong> dejará de aparecer en el ranking y no podrá pasar al escenario.</p>
                <div class="form-group">
                    <label for="reason-{{ $p->id }}">Motivo</label>
                    <textarea class="form-control" id="reason-{{ $p->id }}" name="reason" minlength="8" maxlength="1000" required placeholder="Explica brevemente por qué se inhabilita este equipo"></textarea>
                </div>
                <div class="dialog-actions"><button class="button button-secondary" type="button" onclick="this.closest('dialog').close()">Cancelar</button><button class="button button-danger" type="submit">Inhabilitar</button></div>
            </form>
        </dialog>
    @endforeach
@endif
@endsection
