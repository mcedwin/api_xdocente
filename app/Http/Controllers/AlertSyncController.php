<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Estudiante;
use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertSyncController extends Controller
{
    private function now()
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function alertTypeToDb($flutterValue)
    {
        return match ($flutterValue) {
            'consecutiveAbsences' => 'faltas_consecutivas',
            'performanceDecline' => 'bajo_rendimiento',
            'lowParticipation' => 'baja_participacion',
            'missingTasks' => 'tareas_no_entregadas',
            'multipleDifficulties' => 'dificultades_multiples',
            default => 'faltas_consecutivas',
        };
    }

    private function alertTypeFromDb($dbValue)
    {
        return match ($dbValue) {
            'faltas_consecutivas' => 'consecutiveAbsences',
            'bajo_rendimiento' => 'performanceDecline',
            'baja_participacion' => 'lowParticipation',
            'tareas_no_entregadas' => 'missingTasks',
            'dificultades_multiples' => 'multipleDifficulties',
            default => 'consecutiveAbsences',
        };
    }

    public function index(Request $request)
    {
        $query = Alerta::where('usuario_id', auth()->id())
            ->with(['estudiante', 'curso']);

        if ($request->has('since')) {
            $since = $request->input('since');
            $query->where('updated_at', '>', $since);
        } else {
            $query->whereNull('deleted_at');
        }

        $alertas = $query->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($alerta) {
                return $this->formatAlert($alerta);
            });

        return response()->json(['data' => $alertas]);
    }

    private function formatAlert($alerta)
    {
        $result = [
            'id' => $alerta->uuid ?? (string) $alerta->id,
            'studentId' => $alerta->estudiante->uuid ?? (string) $alerta->estudiante_id,
            'studentName' => $alerta->estudiante->nombre ?? '',
            'courseName' => $alerta->curso->nombre ?? '',
            'courseId' => $alerta->curso->uuid ?? (string) $alerta->curso_id,
            'type' => $this->alertTypeFromDb($alerta->tipo),
            'description' => $alerta->descripcion,
            'date' => $alerta->fecha instanceof \Carbon\Carbon
                ? $alerta->fecha->format('Y-m-d')
                : $alerta->fecha,
            'read' => (bool) $alerta->leida,
            'created_at' => $alerta->created_at,
            'updated_at' => $alerta->updated_at,
        ];

        if ($alerta->deleted_at !== null) {
            $result['deleted_at'] = $alerta->deleted_at;
        }

        $result['sync_status'] = $alerta->sync_status ?? 'synced';

        if ($alerta->device_id !== null) {
            $result['device_id'] = $alerta->device_id;
        }

        return $result;
    }

    public function sync(Request $request)
    {
        $request->validate([
            'alerts' => 'required|array',
            'alerts.*.id' => 'required|string',
            'alerts.*.studentId' => 'required|string',
            'alerts.*.courseId' => 'required|string',
        ]);

        $userId = auth()->id();
        $now = $this->now();

        DB::beginTransaction();
        try {
            Alerta::where('usuario_id', $userId)->forceDelete();

            foreach ($request->alerts as $alertData) {
                $estudiante = Estudiante::where('uuid', $alertData['studentId'])->first();
                $curso = Curso::where('uuid', $alertData['courseId'])->first();

                if (!$estudiante || !$curso) {
                    continue;
                }

                Alerta::create([
                    'usuario_id' => $userId,
                    'uuid' => $alertData['id'],
                    'estudiante_id' => $estudiante->id,
                    'curso_id' => $curso->id,
                    'tipo' => $this->alertTypeToDb($alertData['type'] ?? 'consecutiveAbsences'),
                    'descripcion' => $alertData['description'] ?? '',
                    'fecha' => $alertData['date'] ?? now()->format('Y-m-d'),
                    'leida' => $alertData['read'] ?? false,
                    'created_at' => $alertData['created_at'] ?? $now,
                    'updated_at' => $alertData['updated_at'] ?? $now,
                    'deleted_at' => $alertData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $alertData['device_id'] ?? null,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Alertas sincronizadas']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al sincronizar', 'message' => $e->getMessage()], 500);
        }
    }

    public function markRead($id)
    {
        $now = $this->now();
        $alerta = Alerta::where('uuid', $id)
            ->where('usuario_id', auth()->id())
            ->first();

        if ($alerta) {
            $alerta->update([
                'leida' => true,
                'updated_at' => $now,
            ]);
        }

        return response()->json(['message' => 'Alerta marcada como leída']);
    }
}
