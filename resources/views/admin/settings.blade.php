@extends('layouts.admin')
@section('title', 'Configuración del Sistema')

@section('content')
<header class="page-heading">
    <div>
        <h1>Configuración de la Plataforma</h1>
        <p>Gestión de usuarios con acceso administrativo, parámetros del sistema y seguridad.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="button" data-modal-open="new-user-modal">
            <span class="material-symbols-outlined">person_add</span> Invitar Usuario
        </button>
    </div>
</header>

@if(session('invitationUrl'))
    <div class="card notice-banner" style="background: rgba(4, 46, 128, 0.08); border-left: 4px solid var(--primary); margin-bottom: 24px; padding: 16px 20px;">
        <div style="display: flex; gap: 14px; align-items: flex-start; width: 100%;">
            <span class="material-symbols-outlined" style="color: var(--primary); font-size: 28px;">forward_to_inbox</span>
            <div style="flex: 1;">
                <strong style="font-size: 15px; color: var(--text);">Enlace de activación generado</strong>
                <p style="margin: 4px 0 10px; font-size: 13px; color: var(--text-muted);">
                    Comparte el siguiente enlace directamente con el colaborador para que configure su contraseña e ingrese al panel:
                </p>
                <div style="display: flex; gap: 8px; align-items: center; max-width: 650px;">
                    <input type="text" class="form-control" value="{{ session('invitationUrl') }}" id="invitation-url-input" readonly style="font-family: monospace; font-size: 12px; background: var(--surface);">
                    <button type="button" class="button button-sm button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('invitation-url-input').value); alert('Enlace copiado al portapapeles.');">
                        <span class="material-symbols-outlined">content_copy</span> Copiar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<section class="metric-grid">
    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Administradores</span>
            <span class="metric-icon"><span class="material-symbols-outlined">admin_panel_settings</span></span>
        </div>
        <div class="metric-value">{{ $metrics['administrators'] }}</div>
        <div class="metric-note">Control total del sistema</div>
    </article>
    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Operadores</span>
            <span class="metric-icon accent"><span class="material-symbols-outlined">support_agent</span></span>
        </div>
        <div class="metric-value">{{ $metrics['operators'] }}</div>
        <div class="metric-note">Gestión y mesa de control</div>
    </article>
    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Auditores</span>
            <span class="metric-icon"><span class="material-symbols-outlined">verified</span></span>
        </div>
        <div class="metric-value">{{ $metrics['auditors'] }}</div>
        <div class="metric-note">Supervisión y reportes</div>
    </article>
    <article class="card metric-card">
        <div class="metric-top">
            <span class="metric-label">Protegidos con 2FA</span>
            <span class="metric-icon success"><span class="material-symbols-outlined">shield_lock</span></span>
        </div>
        <div class="metric-value">{{ $metrics['mfa'] }}</div>
        <div class="metric-note">{{ $metrics['total'] }} cuentas registradas</div>
    </article>
</section>

<!-- Gestión de Usuarios -->
<section class="card panel" style="margin-bottom: 24px;">
    <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2>Equipo de Gestión y Roles</h2>
            <p class="form-hint" style="margin: 2px 0 0;">Cuentas autorizadas para operar, configurar o supervisar las competencias.</p>
        </div>
        <button type="button" class="button button-sm button-accent" data-modal-open="new-user-modal">
            <span class="material-symbols-outlined">add</span> Nuevo colaborador
        </button>
    </header>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol asignado</th>
                    <th>Estado de cuenta</th>
                    <th>Autenticación 2FA</th>
                    <th>Último acceso</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    @php
                        $isSelf = $user->id === auth()->user()->id;
                        $roleBadge = match($user->role) {
                            'Administrator' => 'badge-primary',
                            'Operator' => 'badge-live',
                            'Auditor' => 'badge-warning',
                            default => 'badge-muted'
                        };
                        $roleLabel = match($user->role) {
                            'Administrator' => 'Administrador',
                            'Operator' => 'Operador',
                            'Auditor' => 'Auditor',
                            default => $user->role
                        };
                    @endphp
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="avatar" style="width: 34px; height: 34px; font-size: 14px;">
                                    <span class="material-symbols-outlined" style="font-size: 20px;">person</span>
                                </div>
                                <div>
                                    <strong class="table-title">{{ $user->name }}</strong>
                                    @if($isSelf)
                                        <span class="badge badge-sm badge-subtle" style="margin-left: 6px;">Tú</span>
                                    @endif
                                    <div class="table-subtitle">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $roleBadge }}">{{ $roleLabel }}</span>
                        </td>
                        <td>
                            @if($user->status === 'Active')
                                <span class="badge badge-success">Activo</span>
                            @elseif($user->status === 'Pending')
                                <span class="badge badge-warning">Invitación pendiente</span>
                            @else
                                <span class="badge badge-muted">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            @if($user->two_factor_enabled)
                                <span style="display: inline-flex; align-items: center; gap: 4px; color: #16a34a; font-weight: 600; font-size: 13px;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span> Activado
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 13px;">Sin 2FA</span>
                            @endif
                        </td>
                        <td>
                            <span class="table-subtitle">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Sin accesos recientes' }}</span>
                        </td>
                        <td>
                            <div class="table-actions" style="justify-content: flex-end;">
                                @if($user->status === 'Pending')
                                    <form action="{{ route('admin.users.resend', $user) }}" method="post" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="button button-sm button-ghost" title="Reenviar enlace de activación por correo">
                                            <span class="material-symbols-outlined">outgoing_mail</span> Reenviar
                                        </button>
                                    </form>
                                @endif

                                @if(!$isSelf)
                                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="post" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas cambiar el estado de este usuario?');">
                                        @csrf
                                        <button type="submit" class="icon-button" title="{{ $user->status === 'Active' ? 'Desactivar cuenta' : 'Activar cuenta' }}">
                                            <span class="material-symbols-outlined" style="color: {{ $user->status === 'Active' ? 'var(--danger)' : 'var(--success)' }};">
                                                {{ $user->status === 'Active' ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<!-- Parámetros Técnicos del Sistema -->
<section class="card panel">
    <header class="panel-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="material-symbols-outlined" style="color: var(--primary);">memory</span>
            <h2>Especificaciones Técnicas del Entorno</h2>
        </div>
        <span class="status-pill status-live">Operativo</span>
    </header>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Versión de PHP</small>
                <strong style="font-size: 15px; color: var(--text);">PHP {{ $specs['php'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Framework</small>
                <strong style="font-size: 15px; color: var(--text);">Laravel v{{ $specs['laravel'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Motor de Base de Datos</small>
                <strong style="font-size: 15px; color: var(--text); text-transform: uppercase;">{{ $specs['database'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Zona Horaria</small>
                <strong style="font-size: 15px; color: var(--text);">{{ $specs['timezone'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Entorno de Ejecución</small>
                <strong style="font-size: 15px; color: var(--text); text-transform: capitalize;">{{ $specs['environment'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Control de Concurrencia Rayon</small>
                <strong style="font-size: 15px; color: var(--text);">RAYON_NUM_THREADS=1 (cPanel Safe)</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Límite de Memoria</small>
                <strong style="font-size: 15px; color: var(--text);">{{ $specs['memory_limit'] }}</strong>
            </div>
            <div style="background: var(--surface-subtle); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border-subtle);">
                <small style="color: var(--text-muted); display: block; margin-bottom: 4px;">Driver de Sesión / Caché</small>
                <strong style="font-size: 15px; color: var(--text);">{{ $specs['session'] }} / {{ $specs['cache'] }}</strong>
            </div>
        </div>
    </div>
</section>

<!-- Modal: Invitar Nuevo Usuario -->
<div class="modal-backdrop" id="new-user-modal" style="display: none;">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Invitar a un nuevo colaborador</h3>
            <button type="button" class="icon-button" data-modal-close="new-user-modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('admin.users.create') }}" method="post">
            @csrf
            <div class="modal-body stack" style="gap: 16px;">
                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">
                    El colaborador recibirá un correo electrónico con un enlace seguro para activar su cuenta y definir su contraseña personal.
                </p>

                <div class="form-group">
                    <label for="invite-name">Nombre completo</label>
                    <input id="invite-name" class="form-control" type="text" name="name" required maxlength="180" placeholder="Ej. Ana Pérez" autofocus>
                </div>

                <div class="form-group">
                    <label for="invite-email">Correo institucional</label>
                    <input id="invite-email" class="form-control" type="email" name="email" required maxlength="254" placeholder="ejemplo@innovamente.org">
                </div>

                <div class="form-group">
                    <label for="invite-role">Rol de acceso</label>
                    <select id="invite-role" class="form-control" name="role" required>
                        <option value="Operator">Operador (Control de sala, exposiciones y votaciones)</option>
                        <option value="Administrator">Administrador (Control total del sistema y eventos)</option>
                        <option value="Auditor">Auditor (Supervisión de resultados y auditoría de votos)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="button button-ghost" data-modal-close="new-user-modal">Cancelar</button>
                <button type="submit" class="button button-accent">
                    <span class="material-symbols-outlined">send</span> Enviar Invitación
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
