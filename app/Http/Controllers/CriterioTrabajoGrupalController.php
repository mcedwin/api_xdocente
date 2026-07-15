<?php

namespace App\Http\Controllers;

use App\Models\CriterioTrabajoGrupal;
use App\Models\TrabajoGrupal;
use Illuminate\Http\Request;

class CriterioTrabajoGrupalController extends Controller
{
    public function index(TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $criterios = CriterioTrabajoGrupal::where('trabajo_grupal_id', $trabajo->id)->get();
        return response()->json($criterios);
    }

    public function store(Request $request, TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'criterios' => 'required|array',
            'criterios.*.nombre' => 'required|string|max:255',
            'criterios.*.puntaje_maximo' => 'required|numeric|min:0|max:999.99',
        ]);

        $criterios = [];
        foreach ($validated['criterios'] as $data) {
            $criterios[] = CriterioTrabajoGrupal::create([
                'trabajo_grupal_id' => $trabajo->id,
                'nombre' => $data['nombre'],
                'puntaje_maximo' => $data['puntaje_maximo'],
            ]);
        }

        return response()->json($criterios, 201);
    }

    public function update(Request $request, CriterioTrabajoGrupal $criterio)
    {
        $curso = $criterio->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'puntaje_maximo' => 'sometimes|numeric|min:0|max:999.99',
        ]);

        $criterio->update($validated);
        return response()->json($criterio);
    }

    public function destroy(CriterioTrabajoGrupal $criterio)
    {
        $curso = $criterio->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $criterio->delete();
        return response()->json(null, 204);
    }
}
