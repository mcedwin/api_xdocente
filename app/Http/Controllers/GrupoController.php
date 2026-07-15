<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\TrabajoGrupal;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    public function index(TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $grupos = Grupo::where('trabajo_grupal_id', $trabajo->id)
            ->with('estudiantes')
            ->get();
        return response()->json($grupos);
    }

    public function store(Request $request, TrabajoGrupal $trabajo)
    {
        $curso = $trabajo->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'estudiantes' => 'required|array',
            'estudiantes.*' => 'exists:estudiantes,id',
        ]);

        $grupo = Grupo::create([
            'trabajo_grupal_id' => $trabajo->id,
            'nombre' => $validated['nombre'],
        ]);

        $grupo->estudiantes()->attach($validated['estudiantes']);

        $grupo->load('estudiantes');
        return response()->json($grupo, 201);
    }

    public function show(Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $grupo->load(['estudiantes', 'puntajesCriterio.criterio', 'ajustesIndividuales.estudiante']);
        return response()->json($grupo);
    }

    public function update(Request $request, Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'estudiantes' => 'sometimes|array',
            'estudiantes.*' => 'exists:estudiantes,id',
        ]);

        $grupo->update($validated);

        if (isset($validated['estudiantes'])) {
            $grupo->estudiantes()->sync($validated['estudiantes']);
        }

        $grupo->load('estudiantes');
        return response()->json($grupo);
    }

    public function destroy(Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $grupo->delete();
        return response()->json(null, 204);
    }
}
