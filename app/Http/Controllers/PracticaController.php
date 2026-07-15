<?php

namespace App\Http\Controllers;

use App\Models\Practica;
use App\Models\Unidad;
use Illuminate\Http\Request;

class PracticaController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $practicas = Practica::where('unidad_id', $unidad->id)
            ->with('calificaciones.estudiante')
            ->get();
        return response()->json($practicas);
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

        $practica = Practica::create([
            'unidad_id' => $unidad->id,
            'nombre' => $validated['nombre'],
            'fecha' => $validated['fecha'],
        ]);

        return response()->json($practica, 201);
    }

    public function show(Practica $practica)
    {
        $curso = $practica->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $practica->load('calificaciones.estudiante');
        return response()->json($practica);
    }

    public function update(Request $request, Practica $practica)
    {
        $curso = $practica->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'fecha' => 'sometimes|date',
        ]);

        $practica->update($validated);
        return response()->json($practica);
    }

    public function destroy(Practica $practica)
    {
        $curso = $practica->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $practica->delete();
        return response()->json(null, 204);
    }
}
