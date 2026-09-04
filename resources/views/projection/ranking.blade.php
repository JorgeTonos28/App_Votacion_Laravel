@extends('layouts.projection')

@section('title', 'Ranking final')
@section('body-class', 'ranking-body')

@php
    $rows = collect($ranking);
    $podium = $rows->take(3);
    $juryVotes = $rows->sum('juryVotes');
    $publicVotes = $rows->sum('publicVotes');
@endphp

@section('content')
<main class="ranking-stage results-gate {{ $published ? 'is-calculating' : 'is-waiting' }}" data-results-gate data-results-published="{{ $published ? 'true' : 'false' }}" data-results-force-animation="{{ in_array(request()->query('transition'), ['projection', 'lobby'], true) ? 'true' : 'false' }}" data-results-state="{{ route('projection.state', $event->code) }}" data-results-duration="30000" data-event-code="{{ $event->code }}" data-round-number="{{ $state['roundNumber'] }}">
    <div class="ranking-orb ranking-orb-one"></div>
    <div class="ranking-orb ranking-orb-two"></div>
    <div class="ranking-watermark">INNOVAMENTE</div>

    <section class="results-gate-screen" aria-live="polite">
        <div class="results-gate-card">
            <div class="results-waiting-message">
                <span class="results-gate-icon"><img src="{{ asset('favicon.png') }}" alt=""></span>
                <span class="ranking-kicker"><span></span>{{ $event->name }}</span>
                <h1>Los resultados aún no han sido publicados</h1>
                <p>Mantén esta pantalla abierta. El ranking aparecerá automáticamente cuando la organización lo publique.</p>
                <div class="results-waiting-status"><span></span> Esperando publicación</div>
            </div>
            <div class="results-calculating-message">
                <span class="results-gate-icon"><img src="{{ asset('favicon.png') }}" alt=""></span>
                <span class="ranking-kicker"><span></span>{{ $event->name }}</span>
                <h1>Preparando los resultados</h1>
                <p data-results-stage>Recopilando las evaluaciones recibidas…</p>
                <div class="results-progress" aria-hidden="true"><span></span></div>
                <small>Validando votos, ponderaciones y posiciones finales</small>
            </div>
        </div>
    </section>

    @if($published)<div class="ranking-content results-published-content" aria-hidden="true">
        <header class="ranking-heading">
            <div>
                <span class="ranking-kicker"><span></span>{{ $event->name }}</span>
                <h1>Resultados oficiales</h1>
                <p>El talento, la creatividad y la innovación que marcaron esta edición.</p>
            </div>
            <div class="ranking-heading-actions">
                <span class="ranking-live-badge"><span class="material-symbols-outlined">verified</span> Ranking publicado</span>
                <a class="ranking-link-button" href="{{ route('projection.live', $event->code) }}"><span class="material-symbols-outlined">live_tv</span> Volver a proyección</a>
            </div>
        </header>

        <section class="ranking-summary" aria-label="Resumen de resultados">
            <article><span class="material-symbols-outlined">groups</span><div><strong>{{ $rows->count() }}</strong><small>equipos clasificados</small></div></article>
            <article><span class="material-symbols-outlined">gavel</span><div><strong>{{ $juryVotes }}</strong><small>evaluaciones del jurado</small></div></article>
            <article><span class="material-symbols-outlined">how_to_vote</span><div><strong>{{ $publicVotes }}</strong><small>votos del público</small></div></article>
        </section>

        <section class="results-showcase" aria-labelledby="podium-title">
            <div class="results-section-heading">
                <div><span class="section-number">01</span><div><p>Los protagonistas</p><h2 id="podium-title">Podio de ganadores</h2></div></div>
                <span class="section-rule"></span>
            </div>

            <div class="public-podium">
                @foreach([2, 1, 3] as $place)
                    @php $item = $podium->firstWhere('rank', $place); @endphp
                    <article class="public-podium-place place-{{ $place }} {{ $item ? '' : 'is-empty' }}">
                        <div class="podium-person">
                            <span class="podium-medal"><span class="material-symbols-outlined">{{ $place === 1 ? 'workspace_premium' : 'military_tech' }}</span></span>
                            <span class="podium-position">{{ $place }}<sup>{{ $place === 1 ? 'er' : ($place === 2 ? 'do' : 'er') }}</sup></span>
                            <h3>{{ $item['participantName'] ?? 'Por definir' }}</h3>
                            <p>{{ $item['projectTitle'] ?? '—' }}</p>
                            <strong>{{ number_format($item['finalScore'] ?? 0, 2) }} <small>pts</small></strong>
                        </div>
                        <div class="public-podium-block">
                            <span>{{ $place }}</span>
                            @if($place === 1)<span class="material-symbols-outlined podium-star">star</span>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="ranking-visuals">
            <article class="ranking-panel ranking-chart-panel">
                <div class="results-section-heading compact">
                    <div><span class="section-number">02</span><div><p>Comparativa visual</p><h2>Rendimiento por equipo</h2></div></div>
                </div>
                <div class="ranking-chart" role="img" aria-label="Gráfico de barras con el puntaje final de todos los equipos">
                    <div class="ranking-chart-scale" aria-hidden="true"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                    @foreach($rows as $item)
                        <div class="ranking-bar-row">
                            <div class="ranking-bar-label"><span class="ranking-number">{{ $item['rank'] }}</span><div><strong>{{ $item['participantName'] }}</strong><small>{{ $item['projectTitle'] ?: 'Proyecto sin título' }}</small></div></div>
                            <div class="ranking-bar-track">
                                <span class="ranking-bar-fill rank-{{ min(4, $item['rank']) }}" style="--score: {{ max(1, min(100, $item['finalScore'])) }}%">
                                    <span>{{ number_format($item['finalScore'], 2) }}</span>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="ranking-panel ranking-detail-panel">
                <div class="results-section-heading compact">
                    <div><span class="section-number">03</span><div><p>Transparencia</p><h2>Desglose completo</h2></div></div>
                </div>
                <div class="table-wrap">
                    <table class="data-table ranking-table">
                        <thead><tr><th>Pos.</th><th>Equipo y proyecto</th><th>Jurado</th><th>Público</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($rows as $item)
                            <tr>
                                <td><span class="ranking-table-position rank-{{ min(4, $item['rank']) }}">{{ $item['rank'] }}</span></td>
                                <td><div class="table-title">{{ $item['participantName'] }}</div><div class="table-subtitle">{{ $item['projectTitle'] ?: 'Proyecto sin título' }}</div></td>
                                <td><strong>{{ number_format($item['juryScore'], 2) }}</strong><small>{{ $item['juryVotes'] }} votos</small></td>
                                <td><strong>{{ number_format($item['publicScore'], 2) }}</strong><small>{{ $item['publicVotes'] }} votos</small></td>
                                <td><strong class="ranking-total">{{ number_format($item['finalScore'], 2) }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <footer class="ranking-footer"><span>Resultados calculados con ponderación de jurado y público</span><strong>{{ $event->code }}</strong></footer>
    </div>@endif
</main>
<noscript><style>.results-gate.is-calculating .results-gate-screen{display:none}.results-gate.is-calculating .results-published-content{opacity:1;pointer-events:auto;visibility:visible}</style></noscript>
@endsection
