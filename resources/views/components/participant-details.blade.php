@props(['participant', 'dark' => false])

@php
    $members = $participant['participantMembers'] ?? [];
    $area = $participant['participantArea'] ?? null;
    $description = $participant['participantDescription'] ?? null;
    $number = $participant['participantNumber'] ?? null;
@endphp

@if($members || $area || $description || $number)
    <div {{ $attributes->class(['participant-details', 'is-dark' => $dark]) }}>
        @if($number || $area || $members)
            <div class="participant-detail-meta">
                @if($number)
                    <span><span class="material-symbols-outlined">tag</span>Equipo {{ $number }}</span>
                @endif
                @if($area)
                    <span><span class="material-symbols-outlined">category</span>{{ $area }}</span>
                @endif
                @if($members)
                    <span><span class="material-symbols-outlined">groups</span>{{ count($members) }} {{ count($members) === 1 ? 'integrante' : 'integrantes' }}</span>
                @endif
            </div>
        @endif
        @if($members)
            <div class="participant-member-list" aria-label="Integrantes del equipo">
                @foreach($members as $member)<span>{{ $member }}</span>@endforeach
            </div>
        @endif
        @if($description)<p>{{ $description }}</p>@endif
    </div>
@endif
