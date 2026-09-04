<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Support\Domain;
use Illuminate\Support\Str;

class RubricCsvImporter
{
    private const HEADERS = [
        'nombre' => 'name',
        'nombre_criterio' => 'name',
        'name' => 'name',
        'descripcion' => 'description',
        'description' => 'description',
        'peso' => 'weight_percent',
        'peso_porcentaje' => 'weight_percent',
        'weight' => 'weight_percent',
        'weight_percent' => 'weight_percent',
        'escala_minima' => 'scale_min',
        'escala_min' => 'scale_min',
        'scale_min' => 'scale_min',
        'escala_maxima' => 'scale_max',
        'escala_max' => 'scale_max',
        'scale_max' => 'scale_max',
        'etiqueta_minima' => 'minimum_label',
        'minimum_label' => 'minimum_label',
        'etiqueta_maxima' => 'maximum_label',
        'maximum_label' => 'maximum_label',
        'comentario' => 'comment_mode',
        'comentarios' => 'comment_mode',
        'comment_mode' => 'comment_mode',
        'respuesta_obligatoria' => 'required',
        'obligatoria' => 'required',
        'required' => 'required',
        'ayuda_para_evaluar' => 'help_text',
        'ayuda' => 'help_text',
        'help_text' => 'help_text',
    ];

    public function parse(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new DomainException('INVALID_CSV', 'No pudimos leer el archivo CSV.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new DomainException('INVALID_CSV', 'El archivo CSV está vacío.');
            }
            $delimiter = $this->delimiter($firstLine);
            rewind($handle);
            $headerRow = fgetcsv($handle, 0, $delimiter);
            $headers = $this->headers($headerRow ?: []);
            if (! in_array('name', $headers, true)) {
                throw new DomainException('INVALID_CSV', 'El CSV necesita la columna nombre.');
            }

            $criteria = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($this->blank($row)) {
                    continue;
                }
                if (count($criteria) >= 100) {
                    throw new DomainException('INVALID_CSV', 'Puedes importar hasta 100 criterios por rúbrica.');
                }
                $criteria[] = $this->criterion($headers, $row, $line);
            }
        } finally {
            fclose($handle);
        }

        if ($criteria === []) {
            throw new DomainException('INVALID_CSV', 'El CSV no contiene criterios para importar.');
        }

        $names = collect($criteria)->pluck('name')->map(fn ($name) => mb_strtolower($name));
        if ($names->duplicates()->isNotEmpty()) {
            throw new DomainException('INVALID_CSV', 'El CSV contiene nombres de criterios repetidos.');
        }

        return [
            'criteria' => $this->completeWeights($criteria),
            'columns' => array_values(array_unique(array_filter($headers))),
        ];
    }

    private function delimiter(string $line): string
    {
        $choices = [',', ';', "\t"];

        return collect($choices)->sortByDesc(fn ($delimiter) => count(str_getcsv($line, $delimiter)))->first();
    }

    private function headers(array $row): array
    {
        $mapped = [];
        foreach ($row as $index => $header) {
            $normalized = preg_replace('/[^a-z0-9]+/', '_', strtolower(Str::ascii(trim(ltrim((string) $header, "\xEF\xBB\xBF")))));
            $canonical = self::HEADERS[trim($normalized, '_')] ?? null;
            if ($canonical !== null && in_array($canonical, $mapped, true)) {
                throw new DomainException('INVALID_CSV', "La columna {$header} está repetida en el CSV.");
            }
            $mapped[$index] = $canonical;
        }

        return $mapped;
    }

    private function criterion(array $headers, array $row, int $line): array
    {
        $input = [];
        foreach ($headers as $index => $field) {
            if ($field !== null) {
                $input[$field] = trim((string) ($row[$index] ?? ''));
            }
        }
        if (($input['name'] ?? '') === '') {
            throw new DomainException('INVALID_CSV', "Falta el nombre del criterio en la fila {$line}.");
        }

        $criterion = [
            'name' => $this->limited($input['name'], 180, 'nombre', $line),
            'description' => $this->nullableLimited($input['description'] ?? null, 1000, 'descripción', $line),
            'weight_percent' => $this->nullableNumber($input['weight_percent'] ?? null, 'peso', $line),
            'scale_min' => $this->nullableNumber($input['scale_min'] ?? null, 'escala mínima', $line) ?? 1,
            'scale_max' => $this->nullableNumber($input['scale_max'] ?? null, 'escala máxima', $line) ?? 5,
            'minimum_label' => $this->nullableLimited($input['minimum_label'] ?? null, 80, 'etiqueta mínima', $line),
            'maximum_label' => $this->nullableLimited($input['maximum_label'] ?? null, 80, 'etiqueta máxima', $line),
            'required' => $this->boolean($input['required'] ?? null, $line),
            'comment_mode' => $this->commentMode($input['comment_mode'] ?? null, $line),
            'help_text' => $this->nullableLimited($input['help_text'] ?? null, 500, 'ayuda para evaluar', $line),
        ];
        if ($criterion['scale_min'] < 0 || $criterion['scale_max'] > 100 || $criterion['scale_max'] <= $criterion['scale_min']) {
            throw new DomainException('INVALID_CSV', "La escala de la fila {$line} no es válida.");
        }

        return $criterion;
    }

    private function completeWeights(array $criteria): array
    {
        $missing = collect($criteria)->whereNull('weight_percent')->keys();
        $provided = collect($criteria)->sum(fn ($criterion) => $criterion['weight_percent'] ?? 0);
        if ($provided > 100.001) {
            throw new DomainException('INVALID_CSV', 'Los pesos indicados superan el 100%.');
        }
        if ($missing->isEmpty() && abs($provided - 100) > .001) {
            throw new DomainException('INVALID_CSV', 'Los pesos de los criterios deben sumar 100%.');
        }
        if ($missing->isNotEmpty()) {
            $remaining = 100 - $provided;
            if ($remaining <= 0) {
                throw new DomainException('INVALID_CSV', 'No queda peso disponible para los criterios sin peso.');
            }
            $share = round($remaining / $missing->count(), 6);
            foreach ($missing as $position => $index) {
                $criteria[$index]['weight_percent'] = $position === $missing->count() - 1
                    ? round(100 - collect($criteria)->sum(fn ($criterion) => $criterion['weight_percent'] ?? 0), 6)
                    : $share;
            }
        }
        foreach ($criteria as $criterion) {
            if ($criterion['weight_percent'] <= 0 || $criterion['weight_percent'] > 100) {
                throw new DomainException('INVALID_CSV', 'Cada criterio debe tener un peso mayor que 0 y menor o igual a 100%.');
            }
        }

        return $criteria;
    }

    private function nullableNumber(?string $value, string $field, int $line): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = str_replace(['%', ' '], '', trim($value));
        if (! is_numeric($normalized)) {
            throw new DomainException('INVALID_CSV', "El campo {$field} de la fila {$line} debe ser numérico.");
        }

        return (float) $normalized;
    }

    private function boolean(?string $value, int $line): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }
        $normalized = strtolower(Str::ascii(trim($value)));
        if (in_array($normalized, ['1', 'true', 'si', 'yes', 'x', 'obligatorio', 'required'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'opcional', 'optional'], true)) {
            return false;
        }

        throw new DomainException('INVALID_CSV', "Respuesta obligatoria no es válida en la fila {$line}; usa Sí o No.");
    }

    private function commentMode(?string $value, int $line): string
    {
        if ($value === null || trim($value) === '') {
            return Domain::COMMENT_MODES[0];
        }
        $modes = ['hidden' => 'Hidden', 'oculto' => 'Hidden', 'ninguno' => 'Hidden', 'optional' => 'Optional', 'opcional' => 'Optional', 'required' => 'Required', 'requerido' => 'Required', 'obligatorio' => 'Required'];
        $mode = $modes[strtolower(Str::ascii(trim($value)))] ?? null;
        if ($mode === null) {
            throw new DomainException('INVALID_CSV', "Comentario no es válido en la fila {$line}; usa Hidden, Optional o Required.");
        }

        return $mode;
    }

    private function limited(string $value, int $max, string $field, int $line): string
    {
        if (mb_strlen($value) > $max) {
            throw new DomainException('INVALID_CSV', "El campo {$field} de la fila {$line} supera {$max} caracteres.");
        }

        return $value;
    }

    private function nullableLimited(?string $value, int $max, string $field, int $line): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->limited($value, $max, $field, $line);
    }

    private function blank(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }
}
