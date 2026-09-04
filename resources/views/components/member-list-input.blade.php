@props(['members' => [], 'id' => 'team-members'])

@php
    $memberNames = \App\Models\Participant::normalizeMemberNames($members);
    if ($memberNames === []) {
        $memberNames = [''];
    }
@endphp

<div class="form-group member-list-field span-2" data-member-list data-member-limit="30" data-member-prefix="{{ $id }}">
    <div class="member-list-heading">
        <div>
            <label id="{{ $id }}-label">Integrantes</label>
            <p class="form-hint">Agrega una persona por campo. Puedes añadir hasta 30 integrantes.</p>
        </div>
        <span class="member-count" data-member-count>{{ count(array_filter($memberNames)) }} integrantes</span>
    </div>
    <div class="member-input-list" data-member-rows aria-labelledby="{{ $id }}-label">
        @foreach($memberNames as $index => $member)
            <div class="member-input-row" data-member-row>
                <span class="material-symbols-outlined" aria-hidden="true">person</span>
                <input
                    class="form-control"
                    id="{{ $id }}-{{ $index }}"
                    name="members[]"
                    value="{{ $member }}"
                    maxlength="180"
                    autocomplete="name"
                    placeholder="Nombre del integrante"
                    aria-label="Nombre del integrante {{ $index + 1 }}"
                >
                <button class="icon-button member-remove-button" type="button" data-remove-member aria-label="Quitar integrante">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        @endforeach
    </div>
    <button class="button button-secondary member-add-button" type="button" data-add-member>
        <span class="material-symbols-outlined">person_add</span> Agregar integrante
    </button>
    <template data-member-template>
        <div class="member-input-row" data-member-row>
            <span class="material-symbols-outlined" aria-hidden="true">person</span>
            <input class="form-control" name="members[]" maxlength="180" autocomplete="name" placeholder="Nombre del integrante">
            <button class="icon-button member-remove-button" type="button" data-remove-member aria-label="Quitar integrante">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
    </template>
</div>
