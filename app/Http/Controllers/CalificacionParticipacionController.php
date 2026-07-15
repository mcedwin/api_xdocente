<?php

namespace App\Http\Controllers;

use App\Models\CalificacionParticipacion;
use App\Models\ItemParticipacion;
use Illuminate\Http\Request;

class CalificacionParticipacionController extends Controller
{
    public function store(Request $request, ItemParticipacion $item)
    {
        $curso = $item->unidad->curso;
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
            $calificaciones[] = CalificacionParticipacion::updateOrCreate(
                [
                    'item_participacion_id' => $item->id,
                    'estudiante_id' => $data['estudiante_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($calificaciones, 201);
    }

    public function update(Request $request, CalificacionParticipacion $calificacion)
    {
        $curso = $calificacion->itemParticipacion->unidad->curso;
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
