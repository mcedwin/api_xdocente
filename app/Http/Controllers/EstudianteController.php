<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use Illuminate\Http\Request;

class EstudianteController extends Controller
{
    public function index(Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $estudiantes = Estudiante::where('curso_id', $curso->id)->get();
        return response()->json($estudiantes);
    }

    public function store(Request $request, Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'notas' => 'nullable|array',
        ]);

        $estudiante = Estudiante::create([
            'curso_id' => $curso->id,
            'nombre' => $validated['nombre'],
            'notas' => $validated['notas'] ?? null,
        ]);

        return response()->json($estudiante, 201);
    }

    public function show(Estudiante $estudiante)
    {
        $curso = $estudiante->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $estudiante->load([
            'registrosAsistencia',
            'calificacionesTareas',
            'calificacionesPracticas',
            'calificacionesParticipacion',
            'calificacionesProyecto',
        ]);

        return response()->json($estudiante);
    }

    public function update(Request $request, Estudiante $estudiante)
    {
        $curso = $estudiante->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'notas' => 'nullable|array',
        ]);

        $estudiante->update($validated);
        return response()->json($estudiante);
    }

    public function destroy(Estudiante $estudiante)
    {
        $curso = $estudiante->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $estudiante->delete();
        return response()->json(null, 204);
    }
}
