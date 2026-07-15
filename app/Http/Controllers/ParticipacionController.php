<?php

namespace App\Http\Controllers;

use App\Models\ItemParticipacion;
use App\Models\Unidad;
use Illuminate\Http\Request;

class ParticipacionController extends Controller
{
    public function index(Unidad $unidad)
    {
        $curso = $unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $items = ItemParticipacion::where('unidad_id', $unidad->id)
            ->with('calificaciones.estudiante')
            ->get();
        return response()->json($items);
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

        $item = ItemParticipacion::create([
            'unidad_id' => $unidad->id,
            'nombre' => $validated['nombre'],
            'fecha' => $validated['fecha'],
        ]);

        return response()->json($item, 201);
    }

    public function show(ItemParticipacion $item)
    {
        $curso = $item->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $item->load('calificaciones.estudiante');
        return response()->json($item);
    }

    public function update(Request $request, ItemParticipacion $item)
    {
        $curso = $item->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'fecha' => 'sometimes|date',
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    public function destroy(ItemParticipacion $item)
    {
        $curso = $item->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $item->delete();
        return response()->json(null, 204);
    }
}
