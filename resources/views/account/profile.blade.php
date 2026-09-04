@extends('layouts.admin')
@section('title', 'Mi Perfil')

@section('content')
<header class="page-heading">
    <div>
        <h1>Mi Perfil y Seguridad</h1>
        <p>Administra tu información personal, contraseña de acceso y métodos de autenticación.</p>
    </div>
</header>

<div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
    <!-- Datos personales -->
    <section class="card panel">
        <header class="panel-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: var(--primary);">person</span>
                <h2>Datos Personales</h2>
            </div>
        </header>
        <form class="panel-body stack" action="{{ route('admin.profile.update') }}" method="post">
            @csrf
            <div class="form-group">
                <label for="name">Nombre completo</label>
                <input id="name" class="form-control" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="180">
            </div>

            <div class="form-group">
                <label for="email">Correo institucional</label>
                <input id="email" class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="254">
            </div>

            <div class="form-group">
                <label>Rol en la plataforma</label>
                <input class="form-control" type="text" value="{{ $user->role === 'Administrator' ? 'Administrador del Sistema' : ($user->role === 'Operator' ? 'Operador de Mesa de Control' : 'Auditor') }}" disabled readonly style="background: var(--surface-subtle); color: var(--text-muted);">
            </div>

            <div style="margin-top: 12px;">
                <button class="button" type="submit">
                    <span class="material-symbols-outlined">save</span> Guardar cambios
                </button>
            </div>
        </form>
    </section>

    <!-- Cambio de contraseña -->
    <section class="card panel">
        <header class="panel-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: var(--primary);">lock_reset</span>
                <h2>Cambiar Contraseña</h2>
            </div>
        </header>
        <form class="panel-body stack" action="{{ route('admin.profile.password') }}" method="post">
            @csrf
            <div class="form-group">
                <label for="current_password">Contraseña actual</label>
                <input id="current_password" class="form-control" type="password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label for="new_password">Nueva contraseña</label>
                <input id="new_password" class="form-control" type="password" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            </div>

            <div class="form-group">
                <label for="new_password_confirmation">Confirmar nueva contraseña</label>
                <input id="new_password_confirmation" class="form-control" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repite la nueva contraseña">
            </div>

            <div style="margin-top: 12px;">
                <button class="button button-outline" type="submit">
                    <span class="material-symbols-outlined">key</span> Actualizar contraseña
                </button>
            </div>
        </form>
    </section>

    <!-- Segundo factor (2FA) -->
    <section class="card panel" style="grid-column: 1 / -1;">
        <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: {{ $user->two_factor_enabled ? 'var(--success, #16a34a)' : 'var(--warning, #d97706)' }};">shield_lock</span>
                <h2>Autenticación de Dos Factores (2FA)</h2>
            </div>
            @if($user->two_factor_enabled)
                <span class="status-pill status-live">Activo y Protegido</span>
            @else
                <span class="status-pill status-draft">Desactivado</span>
            @endif
        </header>
        <div class="panel-body" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px;">
            <div style="max-width: 650px;">
                <p style="margin: 0 0 8px; color: var(--text); font-size: 15px;">
                    La autenticación de dos factores agrega una capa crítica de seguridad a tu cuenta, solicitando un código temporal de tu aplicación autenticadora (Google Authenticator, Microsoft Authenticator o Authy) cada vez que inicias sesión en un dispositivo nuevo.
                </p>
                <span class="table-subtitle">Recomendado para todos los usuarios con privilegios administrativos u operativos.</span>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                @if($user->two_factor_enabled)
                    <a class="button button-outline" href="{{ route('admin.mfa') }}">
                        <span class="material-symbols-outlined">qr_code</span> Reconfigurar / Ver Códigos
                    </a>
                    <form action="{{ route('admin.mfa.disable') }}" method="post" onsubmit="return confirm('¿Seguro que deseas desactivar la autenticación de dos factores? Tu cuenta será menos segura.');">
                        @csrf
                        <button class="button button-danger" type="submit">
                            <span class="material-symbols-outlined">lock_open</span> Desactivar 2FA
                        </button>
                    </form>
                @else
                    <a class="button button-accent" href="{{ route('admin.mfa') }}">
                        <span class="material-symbols-outlined">verified_user</span> Activar 2FA Ahora
                    </a>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
