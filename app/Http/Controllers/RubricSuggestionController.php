<?php

namespace App\Http\Controllers;

use App\Services\RubricSuggestionService;
use Illuminate\Http\Request;

class RubricSuggestionController extends Controller
{
    /**
     * GET /rubric-suggestions
     * Devuelve la plantilla de criterios sugeridos para cada tipo de actividad.
     */
    public function index(Request $request)
    {
        $data = [];
        foreach (RubricSuggestionService::TYPES as $type => $label) {
            $data[$type] = [
                'type' => $type,
                'label' => $label,
                'criteria' => RubricSuggestionService::template($type),
            ];
        }
        return response()->json(['data' => $data]);
    }

    /**
     * GET /rubric-suggestions/{type}
     * Devuelve la plantilla de criterios sugeridos para un tipo concreto.
     * Se puede pasar ?totalMax=20 para que los puntajes sumen ese máximo.
     */
    public function show($type, Request $request)
    {
        if (!RubricSuggestionService::isValidType($type)) {
            return response()->json([
                'error' => 'Tipo de actividad no válido',
                'valid' => array_keys(RubricSuggestionService::TYPES),
            ], 422);
        }

        $totalMax = (float) ($request->input('totalMax') ?? 15);

        return response()->json([
            'data' => [
                'type' => $type,
                'label' => RubricSuggestionService::typeLabel($type),
                'criteria' => RubricSuggestionService::suggestionsFor($type, $totalMax),
            ],
        ]);
    }
}