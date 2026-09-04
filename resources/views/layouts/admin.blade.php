@php
    $eventId = isset($event) ? $event->id : null;
    $isEventContext = (bool) $eventId;
    $active = fn (string $name) => request()->routeIs($name) ? 'is-active' : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#042E80"><title>@yield('title') · Admin InnovaMente</title><link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}"><link rel="apple-touch-icon" href="{{ asset('favicon.png') }}"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,0&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/site.css') }}">@stack('head')</head>
<body class="admin-body">
<aside class="admin-sidebar" id="admin-sidebar">
    <a class="admin-brand" href="{{ route('admin.index') }}"><span>Admin</span><strong>InnovaMente</strong></a>
    <nav class="admin-nav" aria-label="Navegación administrativa">
        <a class="{{ $active('admin.index') }}" href="{{ route('admin.index') }}"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
        @if(!$isEventContext)
            <a class="{{ $active('admin.projects') }}" href="{{ route('admin.projects') }}"><span class="material-symbols-outlined">inventory_2</span><span>Proyectos</span></a>
            <a class="{{ $active('admin.jurors.all') }}" href="{{ route('admin.jurors.all') }}"><span class="material-symbols-outlined">groups</span><span>Jurados</span></a>
            <a class="{{ $active('admin.results.all') }}" href="{{ route('admin.results.all') }}"><span class="material-symbols-outlined">emoji_events</span><span>Resultados</span></a>
        @else
            <a class="{{ $active('admin.events.edit') }}" href="{{ route('admin.events.edit',$eventId) }}"><span class="material-symbols-outlined">settings</span><span>Configuración del evento</span></a>
            <a class="{{ $active('admin.participants') }}" href="{{ route('admin.participants',$eventId) }}"><span class="material-symbols-outlined">inventory_2</span><span>Participantes</span></a>
            <a class="{{ $active('admin.jurors') }}" href="{{ route('admin.jurors',$eventId) }}"><span class="material-symbols-outlined">groups</span><span>Jurados</span></a>
            <a class="{{ $active('admin.voters') }}" href="{{ route('admin.voters',$eventId) }}"><span class="material-symbols-outlined">how_to_reg</span><span>Votantes</span></a>
            <a class="{{ $active('admin.voting') }}" href="{{ route('admin.voting',$eventId) }}"><span class="material-symbols-outlined">tune</span><span>Evaluación</span></a>
            <a class="{{ $active('admin.live') }}" href="{{ route('admin.live',$eventId) }}"><span class="material-symbols-outlined">live_tv</span><span>Control en vivo</span></a>
            <a class="{{ $active('admin.event.results') }}" href="{{ route('admin.event.results',$eventId) }}"><span class="material-symbols-outlined">emoji_events</span><span>Resultados</span></a>
            <a class="{{ $active('admin.reports') }}" href="{{ route('admin.reports',$eventId) }}"><span class="material-symbols-outlined">summarize</span><span>Reportes</span></a>
        @endif
        <a class="{{ $active('admin.profile') }}" href="{{ route('admin.profile') }}"><span class="material-symbols-outlined">account_circle</span><span>Mi Perfil</span></a>
    </nav>
    @if(auth()->user()->isAdministrator())<a class="admin-settings {{ $active('admin.settings') }}" href="{{ route('admin.settings') }}"><span class="material-symbols-outlined">settings</span><span>Configuración</span></a>@endif
    <a href="{{ route('admin.profile') }}" class="sidebar-user-card {{ $active('admin.profile') }}" title="Ir a mi perfil y seguridad">
        <span class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}</span>
        <div class="user-info">
            <strong>{{ auth()->user()->name ?: auth()->user()->email }}</strong>
            <small>{{ auth()->user()->role === 'Administrator' ? 'Administrador' : (auth()->user()->role === 'Operator' ? 'Operador' : 'Auditor') }}</small>
        </div>
    </a>
    <form class="admin-logout" action="{{ route('admin.logout') }}" method="post">@csrf<button type="submit"><span class="material-symbols-outlined">logout</span> Cerrar sesión</button></form>
</aside>
<div class="admin-shell {{ $isEventContext?'is-event-context':'' }}">
    <header class="admin-topbar" aria-label="Contexto administrativo">
        <button class="icon-button menu-toggle" type="button" data-menu-toggle aria-label="Abrir menú"><span class="material-symbols-outlined">menu</span></button>
        <a class="topbar-logo" href="{{ route('admin.index') }}"><span class="material-symbols-outlined">leaderboard</span><strong>{{ $isEventContext?trim($__env->yieldContent('title')):'InnovaMente' }}</strong></a>
        <div class="admin-user">
            <a class="admin-user-btn" href="{{ route('admin.profile') }}" title="Mi perfil y seguridad" style="display: flex; align-items: center; gap: 8px; text-decoration: none; padding: 4px 12px 4px 4px; border-radius: 999px; background: var(--surface-subtle); border: 1px solid var(--border-subtle);">
                <span class="avatar" style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">{{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}</span>
                <span style="font-size: 13px; font-weight: 600; color: var(--text);">{{ auth()->user()->name ?: auth()->user()->email }}</span>
                <span class="badge badge-sm badge-subtle">{{ auth()->user()->role === 'Administrator' ? 'Admin' : (auth()->user()->role === 'Operator' ? 'Operador' : 'Auditor') }}</span>
            </a>
        </div>
    </header>
    @if(session('success'))<div class="toast toast-success" role="status"><span class="material-symbols-outlined">check_circle</span>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="toast toast-error" role="alert"><span class="material-symbols-outlined">error</span>{{ session('error') }}</div>@endif
    <main class="admin-content @yield('admin-content-class')">
        @if($isEventContext)
            @php
                $switcherEvents = \App\Models\VotingEvent::query()->whereNotIn('status', ['Archived'])->orderByDesc('created_at')->get(['id', 'name', 'code', 'status', 'current_round']);
                $currentRoute = request()->route()?->getName() ?? 'admin.live';
                $isEventRoute = in_array($currentRoute, [
                    'admin.events.edit', 'admin.participants', 'admin.jurors', 'admin.voters', 'admin.voting', 'admin.live', 'admin.event.results', 'admin.reports'
                ], true);
            @endphp
            <div class="event-context-switcher" data-event-switcher>
                <div class="event-switcher-current">
                    <span class="material-symbols-outlined icon">event</span>
                    <div class="event-info">
                        <span class="label">Evento activo:</span>
                        <strong class="name">{{ $event->name }}</strong>
                    </div>
                    <code class="code">{{ $event->code }}</code>
                    <span class="badge {{ in_array($event->status, ['Live', 'LobbyOpen']) ? 'badge-live' : (in_array($event->status, ['Finished', 'Published']) ? 'badge-primary' : 'badge-muted') }}">{{ $event->status }}</span>
                    <span class="badge badge-subtle">Ronda {{ $event->current_round }}</span>
                </div>
                <div class="event-switcher-actions">
                    <div class="dropdown-wrapper" style="position: relative;">
                        <button type="button" class="button button-sm button-outline switcher-toggle-btn" data-switcher-toggle>
                            <span class="material-symbols-outlined">swap_horiz</span>
                            <span>Cambiar evento</span>
                            <span class="material-symbols-outlined arrow">arrow_drop_down</span>
                        </button>
                        <div class="switcher-dropdown-menu" data-switcher-menu style="display: none;">
                            <div class="switcher-header">
                                <span>Cambiar a otro evento</span>
                            </div>
                            <div class="switcher-list">
                                @foreach($switcherEvents as $se)
                                    @php
                                        $targetUrl = $isEventRoute ? route($currentRoute, $se->id) : route('admin.live', $se->id);
                                        $isCurrent = $se->id === $event->id;
                                    @endphp
                                    <a href="{{ $targetUrl }}" class="switcher-item {{ $isCurrent ? 'is-active' : '' }}">
                                        <div class="item-title">
                                            <strong>{{ $se->name }}</strong>
                                            <span class="code">{{ $se->code }}</span>
                                        </div>
                                        <div class="item-meta">
                                            <span class="badge badge-sm {{ in_array($se->status, ['Live', 'LobbyOpen']) ? 'badge-live' : 'badge-muted' }}">{{ $se->status }}</span>
                                            <small class="round-badge">R{{ $se->current_round }}</small>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                            <div class="switcher-footer">
                                <a href="{{ route('admin.projects') }}"><span class="material-symbols-outlined">inventory_2</span> Salir a Directorio de Proyectos</a>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('admin.projects') }}" class="icon-button" title="Salir al listado de proyectos">
                        <span class="material-symbols-outlined">close</span>
                    </a>
                </div>
            </div>
        @endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/site.js') }}"></script>@stack('scripts')
</body>
</html>
