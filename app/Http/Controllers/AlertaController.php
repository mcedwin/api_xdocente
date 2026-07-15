<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    public function index()
    {
        $alertas = Alerta::where('usuario_id', auth()->id())
            ->with(['estudiante', 'curso'])
            ->orderBy('fecha', 'desc')
            ->get();
        return response()->json($alertas);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estudiante_id' => 'required|exists:estudiantes,id',
            'curso_id' => 'required|exists:cursos,id',
            'tipo' => 'required|in:faltas_consecutivas,bajo_rendimiento,baja_participacion,tareas_no_entregadas,dificultades_multiples',
            'descripcion' => 'required|string',
            'fecha' => 'required|date',
        ]);

        $alerta = Alerta::create([
            'usuario_id' => auth()->id(),
            'estudiante_id' => $validated['estudiante_id'],
            'curso_id' => $validated['curso_id'],
            'tipo' => $validated['tipo'],
            'descripcion' => $validated['descripcion'],
            'fecha' => $validated['fecha'],
        ]);

        return response()->json($alerta, 201);
    }

    public function show(Alerta $alerta)
    {
        if ($alerta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $alerta->load(['estudiante', 'curso']);
        return response()->json($alerta);
    }

    public function marcarLeida(Alerta $alerta)
    {
        if ($alerta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $alerta->update(['leida' => true]);
        return response()->json($alerta);
    }

    public function destroy(Alerta $alerta)
    {
        if ($alerta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $alerta->delete();
        return response()->json(null, 204);
    }
}
