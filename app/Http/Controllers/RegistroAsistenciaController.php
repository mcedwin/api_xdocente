<?php

namespace App\Http\Controllers;

use App\Models\RegistroAsistencia;
use App\Models\Sesion;
use Illuminate\Http\Request;

class RegistroAsistenciaController extends Controller
{
    public function index(Sesion $sesion)
    {
        $curso = $sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $registros = RegistroAsistencia::where('sesion_id', $sesion->id)
            ->with('estudiante')
            ->get();
        return response()->json($registros);
    }

    public function store(Request $request, Sesion $sesion)
    {
        $curso = $sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'registros' => 'required|array',
            'registros.*.estudiante_id' => 'required|exists:estudiantes,id',
            'registros.*.asistencia' => 'required|in:presente,ausente,tardanza,justificado',
            'registros.*.observaciones' => 'nullable|string',
        ]);

        $registros = [];
        foreach ($validated['registros'] as $data) {
            $registros[] = RegistroAsistencia::create([
                'sesion_id' => $sesion->id,
                'estudiante_id' => $data['estudiante_id'],
                'asistencia' => $data['asistencia'],
                'observaciones' => $data['observaciones'] ?? null,
            ]);
        }

        return response()->json($registros, 201);
    }

    public function update(Request $request, RegistroAsistencia $registro)
    {
        $curso = $registro->sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'asistencia' => 'sometimes|in:presente,ausente,tardanza,justificado',
            'observaciones' => 'nullable|string',
        ]);

        $registro->update($validated);
        return response()->json($registro);
    }

    public function destroy(RegistroAsistencia $registro)
    {
        $curso = $registro->sesion->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $registro->delete();
        return response()->json(null, 204);
    }
}
