@extends('layouts.public')
@section('title', 'Activar cuenta y crear contraseña')
@section('content')
<main class="ambient-page">
    <div class="public-shell">
        <header class="brand-lockup">
            <img src="{{ asset('images/logo-innovatep.png') }}" alt="INNOVATEP InnovaMente">
            <h1>Activar tu cuenta</h1>
            <p>Hola <strong>{{ $user->name }}</strong>, configura tu contraseña para acceder a la plataforma como <strong>{{ $user->role === 'Administrator' ? 'Administrador' : ($user->role === 'Operator' ? 'Operador' : 'Auditor') }}</strong>.</p>
        </header>
        <form class="card form-card" action="{{ route('auth.invitation.setup', $token) }}" method="post">
            @csrf
            @if($errors->any())
                <div class="validation-summary-errors">
                    <ul>
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group">
                <label>Correo institucional</label>
                <input class="form-control" type="text" value="{{ $user->email }}" disabled readonly style="background: rgba(255,255,255,0.05); color: #cbd5e1;">
            </div>

            <div class="form-group">
                <label for="password">Nueva contraseña</label>
                <input id="password" class="form-control" type="password" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" autofocus>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repite tu contraseña">
            </div>

            <button class="button button-block" type="submit">
                <span class="material-symbols-outlined">check_circle</span> Activar cuenta y entrar
            </button>

            <div class="secure-note">
                <span class="material-symbols-outlined">shield_lock</span>
                <span>Tu contraseña se almacenará de forma encriptada y segura.</span>
            </div>
        </form>
    </div>
</main>
@endsection
