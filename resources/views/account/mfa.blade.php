@extends('layouts.admin')
@section('title', 'Configurar Autenticación 2FA')

@section('content')
<header class="page-heading">
    <div>
        <a class="back-link" href="{{ route('admin.profile') }}" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 13px; text-decoration: none; margin-bottom: 8px;">
            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span> Volver a Mi Perfil
        </a>
        <h1>Autenticación de Dos Factores (2FA)</h1>
        <p>Configura tu aplicación autenticadora para reforzar la seguridad de acceso administrativo.</p>
    </div>
    <span class="badge {{ $user->two_factor_enabled ? 'badge-success' : 'badge-warning' }}">
        {{ $user->two_factor_enabled ? '2FA Activo' : 'Configuración Pendiente' }}
    </span>
</header>

<div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
    <!-- Paso 1: Vincular App -->
    <section class="card panel">
        <header class="panel-header" style="border-bottom: 1px solid var(--border-subtle); padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="step-circle" style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">1</span>
                <h2 style="font-size: 17px; margin: 0;">Vincula tu aplicación</h2>
            </div>
        </header>
        <div class="panel-body stack" style="padding: 24px; gap: 18px; align-items: center; text-align: center;">
            <div style="background: #ffffff; padding: 12px; border-radius: 14px; border: 1px solid var(--border-subtle); box-shadow: 0 4px 12px rgba(0,0,0,0.06); display: inline-block;">
                <img src="{{ $qrDataUri }}" alt="Código QR TOTP" style="width: 170px; height: 170px; display: block;">
            </div>

            <div style="text-align: left; width: 100%;">
                <p style="margin: 0 0 10px; font-size: 14px; color: var(--text); line-height: 1.5;">
                    Escanea este código con tu aplicación autenticadora favorita (Google Authenticator, Microsoft Authenticator, Apple Keychain o Authy).
                </p>
                <div style="background: var(--surface-subtle); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 10px 14px;">
                    <small style="color: var(--text-muted); display: block; margin-bottom: 4px; font-size: 11px; text-transform: uppercase; font-weight: 700;">¿No puedes escanear? Ingresa la clave manualmente:</small>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <code id="manual-key" style="font-family: monospace; font-size: 13px; font-weight: 700; color: var(--primary); letter-spacing: 1.5px; word-break: break-all;">{{ $sharedKey }}</code>
                        <button type="button" class="button button-sm button-ghost" onclick="navigator.clipboard.writeText('{{ $sharedKey }}'); alert('Clave secreta copiada.');" title="Copiar clave" style="padding: 4px 8px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">content_copy</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Paso 2: Confirmar Código -->
    <section class="card panel">
        <header class="panel-header" style="border-bottom: 1px solid var(--border-subtle); padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="step-circle" style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">2</span>
                <h2 style="font-size: 17px; margin: 0;">Confirma el código generado</h2>
            </div>
        </header>
        <form class="panel-body stack" action="{{ route('admin.mfa.enable') }}" method="post" style="padding: 24px; gap: 20px;">
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

            <div>
                <label for="verification-code" style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 8px;">
                    Código de verificación de 6 dígitos
                </label>
                <input id="verification-code" class="form-control" name="verification_code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="Ej. 123456" required style="font-family: monospace; font-size: 22px; letter-spacing: 6px; text-align: center; font-weight: 700; padding: 12px;">
                <small class="form-hint" style="margin-top: 6px; display: block;">Ingresa el código que muestra tu aplicación para comprobar la vinculación.</small>
            </div>

            <div style="margin-top: 10px;">
                <button class="button button-block" type="submit" style="display: flex; justify-content: center; align-items: center; gap: 8px; padding: 12px;">
                    <span class="material-symbols-outlined">shield_lock</span>
                    {{ $user->two_factor_enabled ? 'Verificar y renovar códigos' : 'Activar autenticación 2FA' }}
                </button>
            </div>
        </form>
    </section>
</div>

@if(count($recoveryCodes))
    <section class="card panel" style="margin-bottom: 24px; border-left: 4px solid var(--accent);">
        <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border-subtle);">
            <div>
                <h2 style="font-size: 17px; margin: 0;">Códigos de Recuperación</h2>
                <p class="form-hint" style="margin: 2px 0 0;">Guárdalos en un lugar seguro. Cada código solo puede ser usado una vez si pierdes tu dispositivo.</p>
            </div>
            <button type="button" class="button button-sm button-secondary" onclick="navigator.clipboard.writeText('{{ implode("\n", $recoveryCodes) }}'); alert('Todos los códigos fueron copiados al portapapeles.');">
                <span class="material-symbols-outlined">content_copy</span> Copiar todos
            </button>
        </header>
        <div class="panel-body" style="padding: 24px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                @foreach($recoveryCodes as $code)
                    <div style="background: var(--surface-subtle); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 8px 12px; font-family: monospace; font-size: 14px; font-weight: 700; text-align: center; color: var(--text);">
                        {{ $code }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
