<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\PuntajeCriterioGrupo;
use Illuminate\Http\Request;

class PuntajeCriterioGrupoController extends Controller
{
    public function store(Request $request, Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'puntajes' => 'required|array',
            'puntajes.*.criterio_id' => 'required|exists:criterios_trabajo_grupal,id',
            'puntajes.*.puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $puntajes = [];
        foreach ($validated['puntajes'] as $data) {
            $puntajes[] = PuntajeCriterioGrupo::updateOrCreate(
                [
                    'grupo_id' => $grupo->id,
                    'criterio_id' => $data['criterio_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($puntajes, 201);
    }

    public function update(Request $request, PuntajeCriterioGrupo $puntaje)
    {
        $curso = $puntaje->grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $puntaje->update($validated);
        return response()->json($puntaje);
    }
}
