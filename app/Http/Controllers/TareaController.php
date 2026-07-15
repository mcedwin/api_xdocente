<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\Unidad;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $tareas = Tarea::where('unidad_id', $unidad->id)
            ->with('calificaciones.estudiante')
            ->get();
        return response()->json($tareas);
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

        $tarea = Tarea::create([
            'unidad_id' => $unidad->id,
            'nombre' => $validated['nombre'],
            'fecha' => $validated['fecha'],
        ]);

        return response()->json($tarea, 201);
    }

    public function show(Tarea $tarea)
    {
        $curso = $tarea->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $tarea->load('calificaciones.estudiante');
        return response()->json($tarea);
    }

    public function update(Request $request, Tarea $tarea)
    {
        $curso = $tarea->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'fecha' => 'sometimes|date',
        ]);

        $tarea->update($validated);
        return response()->json($tarea);
    }

    public function destroy(Tarea $tarea)
    {
        $curso = $tarea->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $tarea->delete();
        return response()->json(null, 204);
    }
}
