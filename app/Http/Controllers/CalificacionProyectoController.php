<?php

namespace App\Http\Controllers;

use App\Models\CalificacionProyecto;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class CalificacionProyectoController extends Controller
{
    public function store(Request $request, Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'calificaciones' => 'required|array',
            'calificaciones.*.estudiante_id' => 'required|exists:estudiantes,id',
            'calificaciones.*.criterio_id' => 'required|exists:criterios_proyecto,id',
            'calificaciones.*.puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $calificaciones = [];
        foreach ($validated['calificaciones'] as $data) {
            $calificaciones[] = CalificacionProyecto::updateOrCreate(
                [
                    'proyecto_id' => $proyecto->id,
                    'estudiante_id' => $data['estudiante_id'],
                    'criterio_id' => $data['criterio_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($calificaciones, 201);
    }

    public function update(Request $request, CalificacionProyecto $calificacion)
    {
        $curso = $calificacion->proyecto->unidad->curso;
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
