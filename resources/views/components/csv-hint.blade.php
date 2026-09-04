@props(['columns', 'required' => [], 'note' => null])
<details class="csv-hint" data-csv-hint>
    <summary class="csv-hint-button"><span class="material-symbols-outlined" aria-hidden="true">help</span><span>Ver estructura CSV</span></summary>
    <div class="csv-hint-content">
        <strong>Columnas esperadas</strong>
        <code>{{ implode(',', $columns) }}</code>
        @if(count($required))<small>Obligatorias: {{ implode(', ', $required) }}. Las demás pueden omitirse o dejarse vacías.</small>@endif
        @if($note)<small>{{ $note }}</small>@endif
    </div>
</details>
