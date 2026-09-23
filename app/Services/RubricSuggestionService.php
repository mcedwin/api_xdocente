<?php

namespace App\Services;

/**
 * Plantillas de rúbricas sugeridas por tipo de actividad.
 *
 * Cada plantilla define criterios genéricos (nombre + puntaje base).
 * Al aplicarla a una actividad se reescala para que la suma de los
 * maxScore coincida con el puntaje máximo configurado en el curso
 * para ese tipo de actividad.
 */
class RubricSuggestionService
{
    public const TYPES = [
        'task' => 'Tarea',
        'practice' => 'Práctica',
        'participation' => 'Participación',
        'group_work' => 'Trabajo grupal',
        'project' => 'Proyecto',
    ];

    /**
     * Criterios sugeridos (sin escalar).
     *
     * @return array<int, array{name: string, maxScore: float}>
     */
    public static function template(string $type): array
    {
        return match ($type) {
            'task' => [
                ['name' => 'Contenido y dominio del tema', 'maxScore' => 5],
                ['name' => 'Presentación y claridad', 'maxScore' => 5],
                ['name' => 'Entrega puntual', 'maxScore' => 5],
            ],
            'practice' => [
                ['name' => 'Procedimiento correcto', 'maxScore' => 5],
                ['name' => 'Resultados y análisis', 'maxScore' => 5],
                ['name' => 'Uso adecuado de materiales', 'maxScore' => 5],
            ],
            'participation' => [
                ['name' => 'Participación activa', 'maxScore' => 5],
                ['name' => 'Iniciativa y aportes', 'maxScore' => 5],
                ['name' => 'Respeto y escucha activa', 'maxScore' => 5],
            ],
            'group_work' => [
                ['name' => 'Contenido y calidad', 'maxScore' => 5],
                ['name' => 'Trabajo en equipo', 'maxScore' => 5],
                ['name' => 'Organización', 'maxScore' => 5],
                ['name' => 'Presentación / entrega', 'maxScore' => 5],
            ],
            'project' => [
                ['name' => 'Investigación y contenido', 'maxScore' => 5],
                ['name' => 'Creatividad e innovación', 'maxScore' => 5],
                ['name' => 'Presentación', 'maxScore' => 5],
                ['name' => 'Cumplimiento de requisitos', 'maxScore' => 5],
            ],
            default => [
                ['name' => 'Contenido', 'maxScore' => 5],
                ['name' => 'Presentación', 'maxScore' => 5],
                ['name' => 'Cumplimiento', 'maxScore' => 5],
            ],
        };
    }

    /**
     * Sugerencias reescaladas para que la sumatoria sea $totalMax.
     *
     * @return array<int, array{name: string, maxScore: float}>
     */
    public static function suggestionsFor(string $type, float $totalMax): array
    {
        $criteria = self::template($type);
        $base = array_sum(array_column($criteria, 'maxScore'));
        if ($base <= 0) {
            $base = 1;
        }
        $scale = $totalMax <= 0 ? 1.0 : ($totalMax / $base);
        $scaled = [];
        foreach ($criteria as $c) {
            $scaled[] = [
                'name' => $c['name'],
                'maxScore' => round($c['maxScore'] * $scale, 2),
            ];
        }
        return $scaled;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst($type);
    }

    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }
}