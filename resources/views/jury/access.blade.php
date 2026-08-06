@extends('layouts.public')
@section('title','Acceso de jurado')

@section('content')
<header class="mobile-topbar jury-access-topbar"><a class="wordmark" href="{{ route('home') }}"><span class="material-symbols-outlined">leaderboard</span><strong>InnovaMente</strong></a></header>
<main class="ambient-page has-mobile-topbar"><div class="public-shell">
    <header class="jury-login-header"><span class="material-symbols-outlined">gavel</span><h1>Acceso de Jurado</h1><p>Ingresa tus credenciales para acceder al panel de evaluación.</p></header>
    <form class="card form-card" action="{{ route('jury.validate') }}" method="post">
        @csrf
        @if($errors->any())<div class="validation-summary-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="form-group"><label for="event-code">Código del evento</label><input id="event-code" class="form-control" name="event_code" value="{{ old('event_code',$eventCode??'') }}" placeholder="Ej. BTP726" autocomplete="one-time-code"></div>
        <div class="form-group"><label for="juror-code">Código personal de jurado</label><input id="juror-code" class="form-control" name="juror_code" value="{{ old('juror_code',$jurorCode??'') }}" placeholder="••••-••••" autocomplete="one-time-code"></div>
        <div class="secure-note"><span class="material-symbols-outlined">lock</span><span>Este código es personal e intransferible. Nunca se almacena en texto legible.</span></div>
        <button class="button button-block" type="submit">Verificar identidad <span class="material-symbols-outlined">arrow_forward</span></button>
    </form>
</div></main>
@endsection
