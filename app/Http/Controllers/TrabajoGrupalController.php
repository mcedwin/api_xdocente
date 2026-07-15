<?php

namespace App\Http\Controllers;

use App\Models\TrabajoGrupal;
use App\Models\Unidad;
use Illuminate\Http\Request;

class TrabajoGrupalController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $trabajos = TrabajoGrupal::where('unidad_id', $unidad->id)
            ->with(['criterios', 'grupos.estudiantes', 'grupos.puntajesCriterio.criterio'])
            ->get();
        return response()->json($trabajos);
    }

    public function store(Request $request, Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha' => 'required|date',
        ]);

        $trabajo = TrabajoGrupal::create([
            'unidad_id' => $unidad->id,
            'nombre' => $validated['nombre'],
            'fecha' => $validated['fecha'],
        ]);

        return response()->json($trabajo, 201);
    }

    public function show(TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $trabajo->load(['criterios', 'grupos.estudiantes', 'grupos.puntajesCriterio.criterio', 'grupos.ajustesIndividuales.estudiante']);
        return response()->json($trabajo);
    }

    public function update(Request $request, TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'fecha' => 'sometimes|date',
        ]);

        $trabajo->update($validated);
        return response()->json($trabajo);
    }

    public function destroy(TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $trabajo->delete();
        return response()->json(null, 204);
    }
}
