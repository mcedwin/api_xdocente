<?php

namespace App\Http\Controllers;

use App\Models\CalificacionTarea;
use App\Models\Tarea;
use Illuminate\Http\Request;

class CalificacionTareaController extends Controller
{
    public function store(Request $request, Tarea $tarea)
    {
        $curso = $tarea->unidad->curso;
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
            $calificaciones[] = CalificacionTarea::updateOrCreate(
                [
                    'tarea_id' => $tarea->id,
                    'estudiante_id' => $data['estudiante_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($calificaciones, 201);
    }

    public function update(Request $request, CalificacionTarea $calificacion)
    {
        $curso = $calificacion->tarea->unidad->curso;
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
