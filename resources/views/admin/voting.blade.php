@extends('layouts.admin') @section('title','Configuración de evaluación')
@php $jury=$groups->firstWhere('role_type','Jury');$public=$groups->firstWhere('role_type','Public'); @endphp
@section('content')<header class="page-heading">
<div>
<h1>Evaluación y rúbricas</h1>
<p>Define quién evalúa, el peso de cada audiencia y los criterios que verá cada una.</p>
</div>
<a class="button button-secondary" href="{{ route('admin.events.edit',$event) }}">
<span class="material-symbols-outlined">edit</span> Editar evento</a>
</header>
<section class="card evaluation-intro">
<span class="material-symbols-outlined">groups</span>
<div>
<h2>Audiencias de evaluación</h2>
<p>Jurado y Público son las dos audiencias que aportan al resultado. No son equipos participantes: los equipos se administran en Participantes.</p>
</div>
</section>
@if($locked)<div class="secure-note">
<span class="material-symbols-outlined">lock</span>
<span>La configuración está bloqueada porque el evento ya inició. Conservamos así la consistencia de las evaluaciones.</span>
</div>@else<form class="card admin-form-card evaluation-weights" action="{{ route('admin.voting.weights') }}" method="post">@csrf<input type="hidden" name="event_id" value="{{ $event->id }}">
<h2>Peso de cada audiencia</h2>
<p class="form-hint">La suma debe ser exactamente 100%.</p>
<div class="form-grid">
<div class="form-group">
<label>Jurado (%)</label>
<input class="form-control" type="number" name="jury_weight_percent" min="0" max="100" step=".01" value="{{ $jury->weight*100 }}">
</div>
<div class="form-group">
<label>Público (%)</label>
<input class="form-control" type="number" name="public_weight_percent" min="0" max="100" step=".01" value="{{ $public->weight*100 }}">
</div>
<div class="form-group">
<label>Quórum jurado</label>
<input class="form-control" type="number" name="minimum_juror_votes" min="0" value="{{ $jury->minimum_votes??0 }}">
</div>
<div class="form-group">
<label>Quórum público</label>
<input class="form-control" type="number" name="minimum_public_votes" min="0" value="{{ $public->minimum_votes??0 }}">
</div>
</div>
<div class="weight-bar" aria-label="Distribución de pesos">
<span class="jury" style="width:{{ $jury->weight*100 }}%">
</span>
<span class="public" style="width:{{ $public->weight*100 }}%">
</span>
</div>
<label class="check-row">
<input type="checkbox" name="allow_juror_edit" value="1" @checked($jury->allow_edit_until_close)>
<span>Permitir al jurado editar su evaluación hasta el cierre</span>
</label>
<button class="button" type="submit">
<span class="material-symbols-outlined">save</span> Guardar pesos</button>
</form>@endif
<section class="rubric-editor-section" id="criterios">
<header class="section-heading">
<div>
<h2>Criterios de evaluación</h2>
<p>Cada rúbrica debe sumar 100% internamente.</p>
</div>
</header>
@if(!$locked)
@foreach($groups as $group)<form id="rubric-import-{{ $group->id }}" action="{{ route('admin.voting.rubric.import',$event) }}" method="post" enctype="multipart/form-data">@csrf</form>@endforeach
<form action="{{ route('admin.voting.rubric',$event) }}" method="post" data-rubrics-form>@csrf
@endif
<div class="rubric-editor-grid">@foreach($groups as $group)@php $criteria=$group->criteria->where('enabled',true)->sortBy('sort_order')->values(); $rubricIndex=$loop->index; @endphp<article class="card rubric-editor-card">
<header class="panel-header">
<div>
<span class="badge badge-primary">{{ $group->name }}</span>
<h2>Rúbrica del {{ $group->name }}</h2>
<p class="form-hint">Aporta {{ number_format($group->weight*100) }}% al resultado final.</p>
</div>
</header>
@if($locked)<div class="rubric-list">@foreach($criteria as $criterion)<article class="rubric-row">
<div class="rubric-row-top">
<strong>{{ $criterion->name }}</strong>
<span class="badge badge-muted">{{ number_format($criterion->weight*100) }}%</span>
</div>
<p>{{ $criterion->description }}</p>
<div class="table-subtitle">Escala {{ number_format($criterion->scale_min) }}–{{ number_format($criterion->scale_max) }}</div>
</article>@endforeach</div>
@else<div class="rubric-import"><div class="rubric-import-controls"><input form="rubric-import-{{ $group->id }}" type="hidden" name="voting_group_id" value="{{ $group->id }}"><div><strong>Importar criterios</strong><x-csv-hint :columns="['nombre', 'descripcion', 'peso', 'escala_minima', 'escala_maxima', 'etiqueta_minima', 'etiqueta_maxima', 'comentario', 'respuesta_obligatoria', 'ayuda_para_evaluar']" :required="['nombre']" note="La importación reemplaza esta rúbrica. Si omites peso se reparte el porcentaje disponible; comentario acepta Hidden, Optional o Required y respuesta_obligatoria acepta Sí o No." /></div><input form="rubric-import-{{ $group->id }}" class="form-control" type="file" name="csv" accept=".csv,text/csv" required><button form="rubric-import-{{ $group->id }}" class="button button-secondary" type="submit"><span class="material-symbols-outlined">upload_file</span> Importar CSV</button></div></div><div data-rubric-editor><input type="hidden" name="rubrics[{{ $rubricIndex }}][voting_group_id]" value="{{ $group->id }}">
<div class="criterion-editor-list" data-criterion-list>@foreach($criteria as $i=>$criterion)<fieldset class="criterion-editor" data-criterion-row>
<legend>Criterio <span data-criterion-number>{{ $i+1 }}</span>
</legend>
<input type="hidden" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][id]" value="{{ $criterion->id }}">
<div class="form-group">
<label>Nombre</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][name]" maxlength="180" value="{{ $criterion->name }}" required>
</div>
<div class="form-group">
<label>Descripción</label>
<textarea class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][description]" maxlength="1000">{{ $criterion->description }}</textarea>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Peso (%)</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][weight_percent]" type="number" min=".01" max="100" step=".01" value="{{ $criterion->weight*100 }}" required>
</div>
<div class="form-group">
<label>Escala mínima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][scale_min]" type="number" min="0" max="100" step=".01" value="{{ $criterion->scale_min }}" required>
</div>
<div class="form-group">
<label>Escala máxima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][scale_max]" type="number" min=".01" max="100" step=".01" value="{{ $criterion->scale_max }}" required>
</div>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Etiqueta mínima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][minimum_label]" maxlength="80" value="{{ $criterion->minimum_label }}">
</div>
<div class="form-group">
<label>Etiqueta máxima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][maximum_label]" maxlength="80" value="{{ $criterion->maximum_label }}">
</div>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Comentario</label>
<select class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][comment_mode]">@foreach(\App\Support\Domain::COMMENT_MODES as $mode)<option value="{{ $mode }}" @selected($criterion->comment_mode===$mode)>{{ $mode }}</option>@endforeach</select>
</div>
<label class="check-row">
<input type="checkbox" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][required]" value="1" @checked($criterion->required)>
<span>Respuesta obligatoria</span>
</label>
</div>
<div class="form-group">
<label>Ayuda para evaluar</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][{{ $i }}][help_text]" maxlength="500" value="{{ $criterion->help_text }}">
</div>
<button class="text-button danger-text" type="button" data-remove-criterion>
<span class="material-symbols-outlined">delete</span> Quitar criterio</button>
</fieldset>@endforeach</div>
<template data-rubric-template>
<fieldset class="criterion-editor" data-criterion-row>
<legend>Criterio <span data-criterion-number>
</span>
</legend>
<div class="form-group">
<label>Nombre</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][name]" maxlength="180" required>
</div>
<div class="form-group">
<label>Descripción</label>
<textarea class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][description]" maxlength="1000">
</textarea>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Peso (%)</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][weight_percent]" type="number" min=".01" max="100" step=".01" value="1" required>
</div>
<div class="form-group">
<label>Escala mínima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][scale_min]" type="number" min="0" max="100" step=".01" value="1" required>
</div>
<div class="form-group">
<label>Escala máxima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][scale_max]" type="number" min=".01" max="100" step=".01" value="5" required>
</div>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Etiqueta mínima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][minimum_label]" value="Deficiente">
</div>
<div class="form-group">
<label>Etiqueta máxima</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][maximum_label]" value="Excelente">
</div>
</div>
<div class="form-grid compact-grid">
<div class="form-group">
<label>Comentario</label>
<select class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][comment_mode]">
<option>Hidden</option>
<option>Optional</option>
<option>Required</option>
</select>
</div>
<label class="check-row">
<input type="checkbox" name="rubrics[{{ $rubricIndex }}][criteria][__index__][required]" value="1" checked>
<span>Respuesta obligatoria</span>
</label>
</div>
<div class="form-group">
<label>Ayuda para evaluar</label>
<input class="form-control" name="rubrics[{{ $rubricIndex }}][criteria][__index__][help_text]" maxlength="500">
</div>
<button class="text-button danger-text" type="button" data-remove-criterion>
<span class="material-symbols-outlined">delete</span> Quitar criterio</button>
</fieldset>
</template>
<div class="page-actions rubric-actions">
<button class="button button-secondary" type="button" data-add-criterion>
<span class="material-symbols-outlined">add</span> Agregar criterio</button>
</div>
</div>@endif</article>@endforeach</div>
@if(!$locked)<div class="rubric-save-all">
<div><strong>Guardar todos los cambios</strong><span>Jurado y Público se validarán y guardarán en una sola operación.</span></div>
<button class="button" type="submit"><span class="material-symbols-outlined">save</span> Guardar todas las rúbricas</button>
</div></form>@endif
</section>@endsection
