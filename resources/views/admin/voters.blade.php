@extends('layouts.admin') @section('title','Votantes')
@php $active=$voters->where('status','Active')->count();$voteCount=$voters->sum('votes_count'); @endphp
@section('content')<header class="page-heading">
<div>
<a class="back-link" href="{{ route('admin.index') }}">
<span class="material-symbols-outlined">arrow_back</span> Eventos</a>
<h1>Votantes</h1>
<p>{{ $event->name }} · Acceso {{ $event->public_access_mode }}</p>
</div>
<a class="button button-secondary" href="{{ route('admin.events.edit',$event) }}">
<span class="material-symbols-outlined">settings</span> Configurar acceso</a>
</header>
<section class="metric-grid">
<article class="card metric-card">
<span class="metric-label">Registros</span>
<div class="metric-value">{{ $voters->count() }}</div>
</article>
<article class="card metric-card">
<span class="metric-label">Activos</span>
<div class="metric-value">{{ $active }}</div>
</article>
<article class="card metric-card">
<span class="metric-label">Votos vinculados</span>
<div class="metric-value">{{ $voteCount }}</div>
</article>
</section>
<div class="split-admin voters-layout">
<section class="card panel">
<header class="panel-header">
<div>
<h2>Directorio de acceso</h2>
<p class="form-hint">Los códigos solo se muestran en el CSV al momento de generarlos.</p>
</div>
<span class="badge badge-primary">{{ $voters->count() }} registrados</span>
</header>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr>
<th>Votante</th>
<th>Modalidad</th>
<th>Estado</th>
<th>Último acceso</th>
<th>Votos</th>
<th class="align-right">Acción</th>
</tr>
</thead>
<tbody>@if($voters->isEmpty())<tr>
<td colspan="6"><div class="empty-state"><span class="material-symbols-outlined">how_to_reg</span><h3>Aún no hay votantes registrados</h3><p>En modo dispositivo se registran automáticamente al ingresar al evento.</p></div></td>
</tr>@endif @foreach($voters as $voter)<tr>
<td>
<div class="table-title">{{ $voter->display_name ?? 'Dispositivo anónimo' }}</div>
<div class="table-subtitle">{{ $voter->external_id }}</div>
</td>
<td>{{ $voter->mode }}</td>
<td>
<span class="badge {{ $voter->status==='Active'?'badge-success':'badge-muted' }}">{{ $voter->status }}</span>
</td>
<td>{{ $voter->last_access_at ? $voter->last_access_at->setTimezone($event->time_zone ?: config('app.timezone', 'America/Santo_Domingo'))->format('d/m/Y H:i') : 'Sin actividad' }}</td>
<td>{{ $voter->votes_count }}</td>
<td>
<div class="table-actions">
<form action="{{ route('admin.voters.status',$voter) }}" method="post">@csrf<input type="hidden" name="status" value="{{ $voter->status==='Active'?'Revoked':'Active' }}">
<button class="icon-button" type="submit" title="{{ $voter->status==='Active'?'Revocar acceso':'Reactivar acceso' }}">
<span class="material-symbols-outlined">{{ $voter->status==='Active'?'block':'restart_alt' }}</span>
</button>
</form>
</div>
</td>
</tr>@endforeach</tbody>
</table>
</div>
</section>
<aside class="voters-side-stack">
<section class="card panel voter-action-panel">
<header class="panel-header compact-header">
<h2>Generar códigos</h2>
</header>
<form class="panel-body voter-form-stack" action="{{ route('admin.voters.generate',$event) }}" method="post">@csrf<div class="form-group">
<label for="batch-count">Cantidad</label>
<input id="batch-count" class="form-control" name="count" type="number" min="1" max="500" value="25">
</div>
<div class="form-group">
<label for="batch-prefix">Etiqueta</label>
<input id="batch-prefix" class="form-control" name="label_prefix" value="Invitado">
</div>
<button class="button button-block" type="submit">
<span class="material-symbols-outlined">key</span> Generar y descargar CSV</button>
</form>
</section>
<section class="card panel voter-action-panel">
<header class="panel-header compact-header">
<h2>Importar asistentes</h2>
</header>
<form class="panel-body voter-form-stack" action="{{ route('admin.voters.import',$event) }}" method="post" enctype="multipart/form-data">@csrf<x-csv-hint :columns="['identificador', 'nombre']" :required="['identificador']" note="El identificador puede ser matrícula, correo u otro dato acordado." />
<div class="form-group">
<input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
</div>
<button class="button button-secondary button-block" type="submit">
<span class="material-symbols-outlined">upload_file</span> Importar lista</button>
</form>
</section>
</aside>
</div>@endsection
