@extends('layouts.admin')
@section('title', 'Mi Perfil')

@section('content')
<header class="page-heading">
    <div>
        <h1>Mi Perfil y Seguridad</h1>
        <p>Administra tu identidad institucional, credenciales de acceso y niveles de protección de tu cuenta.</p>
    </div>
</header>

<!-- Tarjeta de Resumen del Usuario -->
<section class="card" style="padding: 24px; margin-bottom: 24px; background: linear-gradient(135deg, var(--surface) 0%, var(--surface-subtle) 100%); border: 1px solid var(--border-subtle); border-radius: 16px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #042E80 0%, #0C58C7 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; box-shadow: 0 4px 12px rgba(4, 46, 128, 0.25); flex-shrink: 0;">
                {{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h2 style="margin: 0; font-size: 22px; font-weight: 800; color: var(--text);">{{ $user->name }}</h2>
                    <span class="badge badge-primary" style="font-weight: 700;">
                        {{ $user->role === 'Administrator' ? 'Administrador del Sistema' : ($user->role === 'Operator' ? 'Operador de Mesa de Control' : 'Auditor') }}
                    </span>
                    @if($user->two_factor_enabled)
                        <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 14px;">shield</span> 2FA Activo
                        </span>
                    @else
                        <span class="badge badge-warning" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 14px;">gpp_maybe</span> 2FA Pendiente
                        </span>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 14px; margin-top: 6px; color: var(--text-muted); font-size: 14px; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">mail</span> {{ $user->email }}
                    </span>
                    <span>•</span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">calendar_today</span> Miembro desde {{ $user->created_at ? $user->created_at->format('M Y') : '2026' }}
                    </span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="#seguridad-2fa" class="button button-sm button-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined" style="font-size: 16px;">security</span> Ver Estado de Seguridad
            </a>
        </div>
    </div>
</section>

<div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
    <!-- Datos personales -->
    <section class="card panel">
        <header class="panel-header" style="border-bottom: 1px solid var(--border-subtle); padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: var(--primary); font-size: 22px;">badge</span>
                <h2 style="font-size: 17px; margin: 0;">Datos Personales</h2>
            </div>
        </header>
        <form class="panel-body stack" action="{{ route('admin.profile.update') }}" method="post" style="padding: 20px; gap: 16px;">
            @csrf
            <div class="form-group">
                <label for="name" style="font-weight: 600; font-size: 13px;">Nombre completo</label>
                <input id="name" class="form-control" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="180">
            </div>

            <div class="form-group">
                <label for="email" style="font-weight: 600; font-size: 13px;">Correo institucional</label>
                <input id="email" class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="254">
            </div>

            <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Rol asignado</label>
                <input class="form-control" type="text" value="{{ $user->role === 'Administrator' ? 'Administrador' : ($user->role === 'Operator' ? 'Operador' : 'Auditor') }}" disabled readonly style="background: var(--surface-subtle); color: var(--text-muted);">
                <small class="form-hint">El rol es gestionado por los administradores de la plataforma.</small>
            </div>

            <div style="margin-top: 8px;">
                <button class="button" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined">save</span> Guardar cambios
                </button>
            </div>
        </form>
    </section>

    <!-- Cambio de contraseña -->
    <section class="card panel">
        <header class="panel-header" style="border-bottom: 1px solid var(--border-subtle); padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="color: var(--primary); font-size: 22px;">lock_reset</span>
                <h2 style="font-size: 17px; margin: 0;">Cambiar Contraseña</h2>
            </div>
        </header>
        <form class="panel-body stack" action="{{ route('admin.profile.password') }}" method="post" style="padding: 20px; gap: 16px;">
            @csrf
            <div class="form-group">
                <label for="current_password" style="font-weight: 600; font-size: 13px;">Contraseña actual</label>
                <input id="current_password" class="form-control" type="password" name="current_password" required autocomplete="current-password" placeholder="Tu contraseña actual">
            </div>

            <div class="form-group">
                <label for="new_password" style="font-weight: 600; font-size: 13px;">Nueva contraseña</label>
                <input id="new_password" class="form-control" type="password" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            </div>

            <div class="form-group">
                <label for="new_password_confirmation" style="font-weight: 600; font-size: 13px;">Confirmar nueva contraseña</label>
                <input id="new_password_confirmation" class="form-control" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repite la nueva contraseña">
            </div>

            <div style="margin-top: 8px;">
                <button class="button button-outline" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined">key</span> Actualizar contraseña
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Segundo factor (2FA) Rediseñado -->
<section class="card panel" id="seguridad-2fa" style="margin-bottom: 24px; border-radius: 16px; overflow: hidden; border-left: 5px solid {{ $user->two_factor_enabled ? '#22c55e' : '#fea203' }};">
    <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); background: var(--surface);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="material-symbols-outlined" style="color: {{ $user->two_factor_enabled ? '#22c55e' : '#fea203' }}; font-size: 28px;">
                {{ $user->two_factor_enabled ? 'verified_user' : 'gpp_maybe' }}
            </span>
            <div>
                <h2 style="font-size: 18px; margin: 0; font-weight: 700;">Autenticación en Dos Factores (2FA)</h2>
                <p class="form-hint" style="margin: 2px 0 0;">Capa de seguridad complementaria para proteger accesos no autorizados.</p>
            </div>
        </div>
        <span class="badge {{ $user->two_factor_enabled ? 'badge-live' : 'badge-warning' }}">
            {{ $user->two_factor_enabled ? '2FA Habilitado' : '2FA No Configurado' }}
        </span>
    </header>

    <div class="panel-body" style="padding: 24px;">
        <div style="display: grid; grid-template-columns: 1fr auto; gap: 32px; align-items: center;">
            <div class="stack" style="gap: 12px; max-width: 720px;">
                <p style="margin: 0; font-size: 15px; line-height: 1.6; color: var(--text);">
                    La autenticación de dos factores (2FA) protege tu cuenta exigiendo un código temporal de 6 dígitos generado por tu aplicación autenticadora en tu teléfono inteligente al iniciar sesión.
                </p>

                <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-top: 6px;">
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-muted);">
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--primary);">smartphone</span>
                        <span>Google Authenticator</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-muted);">
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--primary);">lock</span>
                        <span>Microsoft Authenticator</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-muted);">
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--primary);">key</span>
                        <span>Apple Keychain / 1Password</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                @if($user->two_factor_enabled)
                    <a class="button button-outline" href="{{ route('admin.mfa') }}" style="display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center;">
                        <span class="material-symbols-outlined">qr_code</span> Códigos / Reconfigurar
                    </a>
                    <form action="{{ route('admin.mfa.disable') }}" method="post" onsubmit="return confirm('¿Seguro que deseas desactivar la autenticación de dos factores? Tu cuenta será menos segura.');" style="width: 100%;">
                        @csrf
                        <button class="button button-danger" type="submit" style="display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center;">
                            <span class="material-symbols-outlined">lock_open</span> Desactivar 2FA
                        </button>
                    </form>
                @else
                    <a class="button button-accent" href="{{ route('admin.mfa') }}" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-size: 14px;">
                        <span class="material-symbols-outlined">verified_user</span> Activar 2FA Ahora
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
