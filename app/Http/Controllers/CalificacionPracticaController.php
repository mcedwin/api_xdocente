<?php

namespace App\Http\Controllers;

use App\Models\CalificacionPractica;
use App\Models\Practica;
use Illuminate\Http\Request;

class CalificacionPracticaController extends Controller
{
    public function store(Request $request, Practica $practica)
    {
        $curso = $practica->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'calificaciones' => 'required|array',
            'calificaciones.*.estudiante_id' => 'required|exists:estudiantes,id',
            'calificaciones.*.puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $calificaciones = [];
        foreach ($validated['calificaciones'] as $data) {
            $calificaciones[] = CalificacionPractica::updateOrCreate(
                [
                    'practica_id' => $practica->id,
                    'estudiante_id' => $data['estudiante_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($calificaciones, 201);
    }

    public function update(Request $request, CalificacionPractica $calificacion)
    {
        $curso = $calificacion->practica->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $calificacion->update($validated);
        return response()->json($calificacion);
    }
}
