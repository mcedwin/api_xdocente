<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Unidad;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $proyectos = Proyecto::where('unidad_id', $unidad->id)
            ->with(['criterios', 'calificaciones.estudiante'])
            ->get();
        return response()->json($proyectos);
    }

    public function store(Request $request, Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
        ]);

        $proyecto = Proyecto::create([
            'unidad_id' => $unidad->id,
            'nombre' => $validated['nombre'] ?? 'Proyecto',
        ]);

        return response()->json($proyecto, 201);
    }

    public function show(Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $proyecto->load(['criterios', 'calificaciones.estudiante', 'calificaciones.criterio']);
        return response()->json($proyecto);
    }

    public function update(Request $request, Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
        ]);

        $proyecto->update($validated);
        return response()->json($proyecto);
    }

    public function destroy(Proyecto $proyecto)
    {
        $curso = $proyecto->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $proyecto->delete();
        return response()->json(null, 204);
    }
}
