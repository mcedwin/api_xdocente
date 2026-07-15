<?php

namespace App\Http\Controllers;

use App\Models\AjusteIndividualGrupo;
use App\Models\Grupo;
use Illuminate\Http\Request;

class AjusteIndividualGrupoController extends Controller
{
    public function index(Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $ajustes = AjusteIndividualGrupo::where('grupo_id', $grupo->id)
            ->with(['estudiante', 'criterio'])
            ->get();
        return response()->json($ajustes);
    }

    public function store(Request $request, Grupo $grupo)
    {
        $curso = $grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'ajustes' => 'required|array',
            'ajustes.*.estudiante_id' => 'required|exists:estudiantes,id',
            'ajustes.*.criterio_id' => 'required|exists:criterios_trabajo_grupal,id',
            'ajustes.*.puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $ajustes = [];
        foreach ($validated['ajustes'] as $data) {
            $ajustes[] = AjusteIndividualGrupo::updateOrCreate(
                [
                    'grupo_id' => $grupo->id,
                    'estudiante_id' => $data['estudiante_id'],
                    'criterio_id' => $data['criterio_id'],
                ],
                ['puntaje' => $data['puntaje']]
            );
        }

        return response()->json($ajustes, 201);
    }

    public function update(Request $request, AjusteIndividualGrupo $ajuste)
    {
        $curso = $ajuste->grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'puntaje' => 'required|numeric|min:0|max:999.99',
        ]);

        $ajuste->update($validated);
        return response()->json($ajuste);
    }

    public function destroy(AjusteIndividualGrupo $ajuste)
    {
        $curso = $ajuste->grupo->trabajoGrupal->unidad->curso;
        if ($curso->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $ajuste->delete();
        return response()->json(null, 204);
    }
}
