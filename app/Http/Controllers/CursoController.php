<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    public function index()
    {
        $cursos = Curso::where('usuario_id', auth()->id())
            ->with('unidades')
            ->get();
        return response()->json($cursos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $curso = Curso::create([
            'usuario_id' => auth()->id(),
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        return response()->json($curso, 201);
    }

    public function show(Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $curso->load(['unidades', 'estudiantes']);
        return response()->json($curso);
    }

    public function update(Request $request, Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'indice_unidad_seleccionada' => 'sometimes|integer|min:0',
            'puntaje_max_tarea' => 'sometimes|numeric|min:0|max:999.99',
            'puntaje_max_practica' => 'sometimes|numeric|min:0|max:999.99',
            'puntaje_max_participacion' => 'sometimes|numeric|min:0|max:999.99',
            'puntaje_max_trabajo_grupal' => 'sometimes|numeric|min:0|max:999.99',
            'puntaje_max_proyecto' => 'sometimes|numeric|min:0|max:999.99',
        ]);

        $curso->update($validated);
        return response()->json($curso);
    }

    public function destroy(Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $curso->delete();
        return response()->json(null, 204);
    }
}
