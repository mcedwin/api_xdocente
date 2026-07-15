<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Unidad;
use Illuminate\Http\Request;

class UnidadController extends Controller
{
    public function index(Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $unidades = Unidad::where('curso_id', $curso->id)
            ->orderBy('orden')
            ->get();
        return response()->json($unidades);
    }

    public function store(Request $request, Curso $curso)
    {
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'orden' => 'sometimes|integer|min:0',
        ]);

        $unidad = Unidad::create([
            'curso_id' => $curso->id,
            'nombre' => $validated['nombre'],
            'orden' => $validated['orden'] ?? 0,
        ]);

        return response()->json($unidad, 201);
    }

    public function show(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $unidad->load([
            'sesiones',
            'tareas',
            'practicas',
            'itemsParticipacion',
            'trabajosGrupales',
            'proyectos',
        ]);

        return response()->json($unidad);
    }

    public function update(Request $request, Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'orden' => 'sometimes|integer|min:0',
        ]);

        $unidad->update($validated);
        return response()->json($unidad);
    }

    public function destroy(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $unidad->delete();
        return response()->json(null, 204);
    }
}
