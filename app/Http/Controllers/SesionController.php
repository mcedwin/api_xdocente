<?php

namespace App\Http\Controllers;

use App\Models\Sesion;
use App\Models\Unidad;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $sesiones = Sesion::where('unidad_id', $unidad->id)
            ->orderBy('fecha')
            ->get();
        return response()->json($sesiones);
    }

    public function store(Request $request, Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fecha' => 'required|date',
            'tema' => 'nullable|string|max:255',
        ]);

        $sesion = Sesion::create([
            'unidad_id' => $unidad->id,
            'fecha' => $validated['fecha'],
            'tema' => $validated['tema'] ?? null,
        ]);

        return response()->json($sesion, 201);
    }

    public function show(Sesion $sesion)
    {
        $curso = $sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $sesion->load('registrosAsistencia.estudiante');
        return response()->json($sesion);
    }

    public function update(Request $request, Sesion $sesion)
    {
        $curso = $sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|date',
            'tema' => 'nullable|string|max:255',
        ]);

        $sesion->update($validated);
        return response()->json($sesion);
    }

    public function destroy(Sesion $sesion)
    {
        $curso = $sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $sesion->delete();
        return response()->json(null, 204);
    }
}
