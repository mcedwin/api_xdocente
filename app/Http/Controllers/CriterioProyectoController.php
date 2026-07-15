<?php

namespace App\Http\Controllers;

use App\Models\CriterioProyecto;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class CriterioProyectoController extends Controller
{
    public function index(Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $criterios = CriterioProyecto::where('proyecto_id', $proyecto->id)->get();
        return response()->json($criterios);
    }

    public function store(Request $request, Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
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
            $criterios[] = CriterioProyecto::create([
                'proyecto_id' => $proyecto->id,
                'nombre' => $data['nombre'],
                'puntaje_maximo' => $data['puntaje_maximo'],
            ]);
        }

        return response()->json($criterios, 201);
    }

    public function update(Request $request, CriterioProyecto $criterio)
    {
        $curso = $criterio->proyecto->unidad->curso;
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

    public function destroy(CriterioProyecto $criterio)
    {
        $curso = $criterio->proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $criterio->delete();
        return response()->json(null, 204);
    }
}
