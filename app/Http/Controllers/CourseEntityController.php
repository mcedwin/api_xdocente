<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Alerta;
use App\Models\Unidad;
use App\Models\Sesion;
use App\Models\RegistroAsistencia;
use App\Models\Activity;
use App\Models\ActivityCriterion;
use App\Models\ActivityGroup;
use App\Models\ActivityScore;
use App\Models\ActivityGroupOverride;
use App\Services\RubricSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseEntityController extends Controller
{
    // ===================== HELPERS =====================

    private function now()
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function softDelete($model)
    {
        $now = $this->now();
        $model->update([
            'deleted_at' => $now,
            'updated_at' => $now,
            'sync_status' => 'synced',
        ]);
    }

    private function touchCourse($curso)
    {
        $curso->update(['updated_at' => $this->now()]);
    }

    /**
     * Mapa uuid→id de los estudiantes del curso, construido una sola vez por
     * request. Evita el N+1 de resolver cada estudiante con 1 SELECT por fila
     * en asistencias, puntajes individuales, miembros de grupo y overrides.
     */
    private function studentMap(Curso $curso): array
    {
        return Estudiante::where('curso_id', $curso->id)
            ->pluck('id', 'uuid')
            ->all();
    }

    private function findCourse($uuid)
    {
        return Curso::where('uuid', $uuid)->where('usuario_id', auth()->id())->firstOrFail();
    }

    private function findUnit($course, $unitUuid)
    {
        return Unidad::where('uuid', $unitUuid)->where('curso_id', $course->id)->firstOrFail();
    }

    private function findActivity($unidad, $uuid, $type = null)
    {
        $query = Activity::where('uuid', $uuid)->where('unidad_id', $unidad->id);
        if ($type) {
            $query->where('type', $type);
        }
        return $query->firstOrFail();
    }

    private function attendanceToDb($flutterValue)
    {
        return match ($flutterValue) {
            'present' => 'presente',
            'absent' => 'ausente',
            'late' => 'tardanza',
            'justified' => 'justificado',
            default => 'presente',
        };
    }

    private function syncFields(Request $request, $now)
    {
        return [
            'updated_at' => $request->input('updated_at') ?? $now,
            'sync_status' => 'synced',
            'device_id' => $request->input('device_id') ?? null,
            'deleted_at' => $request->input('deleted_at') ?? null,
        ];
    }

    /** Puntaje máximo configurado en el curso para un tipo de actividad. */
    private function maxScoreForType(Curso $curso, string $type): float
    {
        return match ($type) {
            'task' => (float) ($curso->puntaje_max_tarea ?? 5),
            'practice' => (float) ($curso->puntaje_max_practica ?? 5),
            'participation' => (float) ($curso->puntaje_max_participacion ?? 3),
            'group_work' => (float) ($curso->puntaje_max_trabajo_grupal ?? 20),
            'project' => (float) ($curso->puntaje_max_proyecto ?? 5),
            default => 5,
        };
    }

    /** Valores por defecto de las banderas cuando el cliente no las envía (compatibilidad). */
    private function defaultFlags(string $type): array
    {
        return match ($type) {
            'group_work' => ['is_group_based' => true, 'uses_rubric' => true],
            'project' => ['is_group_based' => false, 'uses_rubric' => true],
            default => ['is_group_based' => false, 'uses_rubric' => false],
        };
    }

    // ===================== 4.1 STUDENTS =====================

    public function storeStudent($courseUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string']);

        $est = Estudiante::where('uuid', $request->id)->where('curso_id', $curso->id)->first();
        if ($est) {
            $est->update([
                'nombre' => $request->name,
                'notas' => $request->notes ?? null,
                'updated_at' => $request->input('updated_at') ?? $now,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
                'deleted_at' => $request->input('deleted_at') ?? null,
            ]);
        } else {
            Estudiante::create([
                'uuid' => $request->id, 'curso_id' => $curso->id,
                'nombre' => $request->name, 'notas' => $request->notes ?? null,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);
        }

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function updateStudent($courseUuid, $studentUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $est = Estudiante::where('uuid', $studentUuid)->where('curso_id', $curso->id)->firstOrFail();

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        if ($request->has('notes')) { $data['notas'] = $request->notes; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        $est->update($data);

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyStudent($courseUuid, $studentUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $est = Estudiante::where('uuid', $studentUuid)->where('curso_id', $curso->id)->first();
        if ($est) {
            // Cascada: al eliminar un estudiante también se eliminan (soft) sus
            // notas, registros de asistencia, alertas y overrides de grupo, para
            // no dejar registros huérfanos que serializarían studentId numérico
            // sin estudiante en el sync.
            $this->softDelete($est);

            ActivityScore::where('estudiante_id', $est->id)->whereNull('deleted_at')
                ->get()->each(fn ($s) => $this->softDelete($s));
            RegistroAsistencia::where('estudiante_id', $est->id)->whereNull('deleted_at')
                ->get()->each(fn ($r) => $this->softDelete($r));
            ActivityGroupOverride::where('estudiante_id', $est->id)->whereNull('deleted_at')
                ->get()->each(fn ($o) => $this->softDelete($o));
            Alerta::where('estudiante_id', $est->id)->whereNull('deleted_at')
                ->get()->each(fn ($a) => $this->softDelete($a));

            // Membresías de grupos: tabla pivote sin soft-delete, se remueven
            // directamente (mismo patrón que updateActivityGroup).
            DB::table('app_activity_group_members')->where('estudiante_id', $est->id)->delete();
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function storeStudentsBulk($courseUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $request->validate(['students' => 'required|array', 'students.*.name' => 'required|string']);

        DB::beginTransaction();
        try {
            foreach ($request->students as $s) {
                Estudiante::create([
                    'uuid' => $s['id'] ?? Str::uuid()->toString(),
                    'curso_id' => $curso->id,
                    'nombre' => $s['name'],
                    'notas' => $s['notes'] ?? null,
                    'created_at' => $s['created_at'] ?? $now,
                    'updated_at' => $s['updated_at'] ?? $now,
                    'sync_status' => 'synced',
                    'device_id' => $s['device_id'] ?? null,
                ]);
            }
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===================== 4.2 UNITS =====================

    public function storeUnit($courseUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string']);

        Unidad::create([
            'uuid' => $request->id, 'curso_id' => $curso->id,
            'nombre' => $request->name, 'orden' => 0,
            'created_at' => $request->input('created_at') ?? $now,
            'updated_at' => $request->input('updated_at') ?? $now,
            'deleted_at' => $request->input('deleted_at') ?? null,
            'sync_status' => 'synced',
            'device_id' => $request->input('device_id') ?? null,
        ]);

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function updateUnit($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $unidad = $this->findUnit($curso, $unitUuid);

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        $unidad->update($data);

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyUnit($courseUuid, $unitUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);

        $sesiones = Sesion::where('unidad_id', $unidad->id)->get();
        foreach ($sesiones as $sesion) {
            $sesion->registrosAsistencia()->update([
                'deleted_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
            $this->softDelete($sesion);
        }

        $activities = Activity::where('unidad_id', $unidad->id)->get();
        foreach ($activities as $activity) {
            $this->softDeleteActivity($activity);
        }

        $this->softDelete($unidad);

        $this->touchCourse($curso);

        return response()->json(['ok' => true]);
    }

    // ===================== 4.3 SESSIONS =====================

    public function storeSession($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'date' => 'required|date']);

        DB::beginTransaction();
        try {
            $sesion = Sesion::create([
                'uuid' => $request->id, 'unidad_id' => $unidad->id,
                'fecha' => $request->date, 'tema' => $request->topic ?? null,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            $studentIdsByUuid = $this->studentMap($curso);
            foreach ($request->records ?? [] as $rec) {
                $estId = $studentIdsByUuid[$rec['studentId']] ?? null;
                if ($estId) {
                    RegistroAsistencia::create([
                        'uuid' => Str::uuid()->toString(), 'sesion_id' => $sesion->id,
                        'estudiante_id' => $estId,
                        'asistencia' => $this->attendanceToDb($rec['attendance'] ?? 'present'),
                        'observaciones' => $rec['observations'] ?? null,
                    ]);
                }
            }
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateSession($courseUuid, $unitUuid, $sessionUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $sesion = Sesion::where('uuid', $sessionUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        DB::beginTransaction();
        try {
            $data = [];
            if ($request->has('date')) { $data['fecha'] = $request->date; }
            if ($request->has('topic')) { $data['tema'] = $request->topic; }
            $data['updated_at'] = $request->input('updated_at') ?? $now;
            $data['sync_status'] = 'synced';
            $data['device_id'] = $request->input('device_id') ?? null;
            $data['deleted_at'] = $request->input('deleted_at') ?? null;
            if (!empty($data)) { $sesion->update($data); }

            if ($request->has('records')) {
                $sesion->registrosAsistencia()->forceDelete();
                $studentIdsByUuid = $this->studentMap($curso);
                foreach ($request->records as $rec) {
                    $estId = $studentIdsByUuid[$rec['studentId']] ?? null;
                    if ($estId) {
                        RegistroAsistencia::create([
                            'uuid' => Str::uuid()->toString(), 'sesion_id' => $sesion->id,
                            'estudiante_id' => $estId,
                            'asistencia' => $this->attendanceToDb($rec['attendance'] ?? 'present'),
                            'observaciones' => $rec['observations'] ?? null,
                        ]);
                    }
                }
            }
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroySession($courseUuid, $unitUuid, $sessionUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $sesion = Sesion::where('uuid', $sessionUuid)->where('unidad_id', $unidad->id)->first();
        if ($sesion) {
            // Soft-delete en cascada de los registros de asistencia.
            $sesion->registrosAsistencia()->update([
                'deleted_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
            $this->softDelete($sesion);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    // ===================== ACTIVITY (UNIFICADO) =====================

    private function softDeleteActivity(Activity $activity)
    {
        $this->softDelete($activity);

        $activity->criteria()->update([
            'deleted_at' => $activity->deleted_at,
            'updated_at' => $activity->deleted_at,
            'sync_status' => 'synced',
        ]);

        $activity->groups()->update([
            'deleted_at' => $activity->deleted_at,
            'updated_at' => $activity->deleted_at,
            'sync_status' => 'synced',
        ]);

        $activity->scores()->update([
            'deleted_at' => $activity->deleted_at,
            'updated_at' => $activity->deleted_at,
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Reemplaza los criterios (rúbrica) de la actividad.
     * Si $usesRubric es false se deja la actividad sin criterios (nota simple).
     * Si no se reciben criterios y la actividad usa rúbrica, se aplican sugerencias.
     */
    private function syncCriteria(Activity $activity, $criteria, bool $usesRubric, float $totalMax)
    {
        $activity->criteria()->forceDelete();

        if (!$usesRubric) {
            return;
        }

        $criteria = is_array($criteria) ? $criteria : [];
        if (empty($criteria)) {
            $criteria = RubricSuggestionService::suggestionsFor($activity->type, $totalMax);
        }

        $i = 0;
        foreach ($criteria as $c) {
            $c = (array) $c;
            $name = (!empty($c['name'])) ? $c['name'] : 'Criterio ' . ($i + 1);
            $max = (isset($c['maxScore']) && $c['maxScore'] !== '' && $c['maxScore'] !== null)
                ? (float) $c['maxScore']
                : 5;
            ActivityCriterion::create([
                'uuid' => $c['id'] ?? Str::uuid()->toString(),
                'activity_id' => $activity->id,
                'nombre' => $name,
                'puntaje_maximo' => $max,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
                'sync_status' => 'synced',
                'device_id' => $c['device_id'] ?? null,
            ]);
            $i++;
        }
    }

    /**
     * Reemplaza los puntajes individuales.
     * - Sin rúbrica:  scores = [{studentId, score}]
     * - Con rúbrica:  scores = [{studentId, criterionScores: {critUuid: n}}]
     */
    private function replaceIndividualScores(Activity $activity, $scores, Curso $curso)
    {
        $activity->scores()->whereNull('grupo_id')->forceDelete();

        $criteriaMap = $activity->criteria->pluck('id', 'uuid');
        $studentIdsByUuid = $this->studentMap($curso);
        foreach ($scores ?? [] as $sc) {
            $sc = (array) $sc;
            $estId = isset($sc['studentId']) ? ($studentIdsByUuid[$sc['studentId']] ?? null) : null;
            if (!$estId) {
                continue;
            }

            if ($activity->uses_rubric) {
                foreach ($sc['criterionScores'] ?? [] as $critUuid => $puntaje) {
                    $critId = $criteriaMap[$critUuid] ?? null;
                    if (!$critId) {
                        continue;
                    }
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'estudiante_id' => $estId,
                        'criterio_id' => $critId,
                        'grupo_id' => null,
                        'puntaje' => $puntaje,
                    ]);
                }
            } else {
                ActivityScore::create([
                    'uuid' => Str::uuid()->toString(),
                    'activity_id' => $activity->id,
                    'estudiante_id' => $estId,
                    'criterio_id' => null,
                    'grupo_id' => null,
                    'puntaje' => $sc['score'] ?? 0,
                ]);
            }
        }
    }

    /**
     * Reemplaza los grupos de la actividad.
     * - Con rúbrica:  groups = [{id?, name, studentIds, criterionScores, overrides}]
     * - Sin rúbrica:   groups = [{id?, name, studentIds, score}]
     */
    private function replaceGroups(Activity $activity, $groups, Curso $curso)
    {
        foreach ($activity->groups as $g) {
            ActivityGroupOverride::where('grupo_id', $g->id)->forceDelete();
            ActivityScore::where('grupo_id', $g->id)->forceDelete();
            DB::table('app_activity_group_members')->where('grupo_id', $g->id)->delete();
            $g->forceDelete();
        }

        $criteriaMap = $activity->criteria->pluck('id', 'uuid');
        $studentIdsByUuid = $this->studentMap($curso);
        foreach ($groups ?? [] as $i => $gData) {
            $gData = (array) $gData;
            $grupo = ActivityGroup::create([
                'uuid' => $gData['id'] ?? Str::uuid()->toString(),
                'activity_id' => $activity->id,
                'nombre' => $gData['name'] ?? ('Grupo ' . ($i + 1)),
                'created_at' => $gData['created_at'] ?? $this->now(),
                'updated_at' => $gData['updated_at'] ?? $this->now(),
                'deleted_at' => $gData['deleted_at'] ?? null,
                'sync_status' => 'synced',
                'device_id' => $gData['device_id'] ?? null,
            ]);

            foreach ($gData['studentIds'] ?? [] as $estUuid) {
                $estId = $studentIdsByUuid[$estUuid] ?? null;
                if ($estId) {
                    DB::table('app_activity_group_members')->insert([
                        'grupo_id' => $grupo->id,
                        'estudiante_id' => $estId,
                    ]);
                }
            }

            if ($activity->uses_rubric) {
                foreach ($gData['criterionScores'] ?? [] as $critUuid => $puntaje) {
                    $critId = $criteriaMap[$critUuid] ?? null;
                    if (!$critId) {
                        continue;
                    }
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'grupo_id' => $grupo->id,
                        'criterio_id' => $critId,
                        'estudiante_id' => null,
                        'puntaje' => $puntaje,
                    ]);
                }

                foreach ($gData['overrides'] ?? [] as $ov) {
                    $ov = (array) $ov;
                    $estId = isset($ov['studentId']) ? ($studentIdsByUuid[$ov['studentId']] ?? null) : null;
                    if (!$estId) {
                        continue;
                    }
                    foreach ($ov['criterionScores'] ?? [] as $critUuid => $puntaje) {
                        $critId = $criteriaMap[$critUuid] ?? null;
                        if (!$critId) {
                            continue;
                        }
                        ActivityGroupOverride::create([
                            'uuid' => Str::uuid()->toString(),
                            'grupo_id' => $grupo->id,
                            'estudiante_id' => $estId,
                            'criterio_id' => $critId,
                            'puntaje' => $puntaje,
                        ]);
                    }
                }
            } else {
                ActivityScore::create([
                    'uuid' => Str::uuid()->toString(),
                    'activity_id' => $activity->id,
                    'grupo_id' => $grupo->id,
                    'criterio_id' => null,
                    'estudiante_id' => null,
                    'puntaje' => $gData['score'] ?? 0,
                ]);
            }
        }
    }

    /**
     * Sincroniza los hijos de una actividad (criterios, puntajes individuales y grupos)
     * en función de lo que envíe el payload y de si es creación o actualización.
     */
    private function syncActivityChildren(Activity $activity, Curso $curso, Request $request, bool $isCreate)
    {
        $totalMax = $this->maxScoreForType($curso, $activity->type);

        $hasCriteriaPayload = $request->has('criteria');
        $toggledRubricOn = $request->has('usesRubric')
            && (bool) $request->input('usesRubric')
            && !(bool) $activity->uses_rubric;
        $shouldSyncCriteria = $hasCriteriaPayload
            || $toggledRubricOn
            || ($isCreate && $activity->uses_rubric);

        if ($shouldSyncCriteria) {
            $criteria = $request->input('criteria', []);
            // No se enviaron criterios y la rúbrica se activó -> sugerencias
            if (empty($criteria) && $activity->uses_rubric) {
                $criteria = RubricSuggestionService::suggestionsFor($activity->type, $totalMax);
            }
            $this->syncCriteria($activity, $criteria, (bool) $activity->uses_rubric, $totalMax);
        }

        // Si se desactivó la rúbrica, eliminar puntajes por criterio (quedan los simples)
        if ($request->has('usesRubric') && !(bool) $request->input('usesRubric')) {
            $activity->scores()->whereNotNull('criterio_id')->forceDelete();
            ActivityGroupOverride::whereIn(
                'grupo_id',
                $activity->groups()->pluck('id')
            )->forceDelete();
        }

        if ($request->has('scores')) {
            $this->replaceIndividualScores($activity, $request->input('scores', []), $curso);
        }

        if ($request->has('groups')) {
            $this->replaceGroups($activity, $request->input('groups', []), $curso);
        }
    }

    /**
     * Crea una actividad de cualquier tipo (almacenada en app_activities).
     * La rúbrica es opcional (usesRubric) y puede ser individual o grupal (isGroupBased).
     */
    public function storeActivity($courseUuid, $unitUuid, $type, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();

        $request->validate(['name' => 'required|string']);

        $defaults = $this->defaultFlags($type);

        DB::beginTransaction();
        try {
            $activity = Activity::create([
                'uuid' => $request->input('id') ?? Str::uuid()->toString(),
                'unidad_id' => $unidad->id,
                'type' => $type,
                'nombre' => $request->name,
                'fecha' => $request->input('date') ?? $now,
                'is_group_based' => $request->input('isGroupBased', $defaults['is_group_based']),
                'uses_rubric' => $request->input('usesRubric', $defaults['uses_rubric']),
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            $this->syncActivityChildren($activity, $curso, $request, true);

            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza una actividad de cualquier tipo (criterios, puntajes, grupos y banderas).
     */
    public function updateActivity($courseUuid, $unitUuid, $uuid, $type, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $activity = $this->findActivity($unidad, $uuid, $type);

        DB::beginTransaction();
        try {
            $data = [];
            if ($request->has('name')) { $data['nombre'] = $request->name; }
            if ($request->has('date')) { $data['fecha'] = $request->date; }
            if ($request->has('isGroupBased')) { $data['is_group_based'] = $request->input('isGroupBased'); }
            if ($request->has('usesRubric')) { $data['uses_rubric'] = $request->input('usesRubric'); }
            $data['updated_at'] = $request->input('updated_at') ?? $now;
            $data['sync_status'] = 'synced';
            $data['device_id'] = $request->input('device_id') ?? null;
            $data['deleted_at'] = $request->input('deleted_at') ?? null;
            if (!empty($data)) { $activity->update($data); }

            $this->syncActivityChildren($activity, $curso, $request, false);

            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyActivity($courseUuid, $unitUuid, $uuid, $type)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivity($unidad, $uuid, $type);
        $this->softDeleteActivity($activity);
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    /** Actualiza únicamente la rúbrica (criterios) de una actividad. */
    public function updateActivityCriteria($courseUuid, $unitUuid, $uuid, $type, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivity($unidad, $uuid, $type);
        $now = $this->now();

        DB::beginTransaction();
        try {
            $usesRubric = $request->has('usesRubric')
                ? (bool) $request->input('usesRubric')
                : true;
            $activity->update([
                'uses_rubric' => $usesRubric,
                'updated_at' => $now,
                'sync_status' => 'synced',
            ]);
            $this->syncCriteria($activity, $request->input('criteria', []), $usesRubric, $this->maxScoreForType($curso, $activity->type));
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Actualiza los puntajes individuales de una actividad. */
    public function updateActivityScores($courseUuid, $unitUuid, $uuid, $type, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivity($unidad, $uuid, $type);
        $now = $this->now();

        DB::beginTransaction();
        try {
            $this->replaceIndividualScores($activity, $request->input('scores', []), $curso);
            $activity->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===================== GRUPOS (cualquier tipo de actividad) =====================

    private function findActivityAny($unidad, $uuid)
    {
        return Activity::where('uuid', $uuid)->where('unidad_id', $unidad->id)->firstOrFail();
    }

    public function storeActivityGroup($courseUuid, $unitUuid, $activityUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $activity = $this->findActivityAny($unidad, $activityUuid);
        $request->validate(['id' => 'required|string', 'name' => 'required|string']);

        DB::beginTransaction();
        try {
            $grupo = ActivityGroup::create([
                'uuid' => $request->id,
                'activity_id' => $activity->id,
                'nombre' => $request->name,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            $studentIdsByUuid = $this->studentMap($curso);
            foreach ($request->studentIds ?? [] as $estUuid) {
                $estId = $studentIdsByUuid[$estUuid] ?? null;
                if ($estId) {
                    DB::table('app_activity_group_members')->insert(['grupo_id' => $grupo->id, 'estudiante_id' => $estId]);
                }
            }

            if (!$activity->uses_rubric) {
                ActivityScore::create([
                    'uuid' => Str::uuid()->toString(),
                    'activity_id' => $activity->id,
                    'grupo_id' => $grupo->id,
                    'criterio_id' => null,
                    'estudiante_id' => null,
                    'puntaje' => $request->input('score', 0),
                ]);
            } else {
                $criteriaMap = $activity->criteria->pluck('id', 'uuid');
                foreach ($request->criterionScores ?? [] as $critUuid => $score) {
                    $critId = $criteriaMap[$critUuid] ?? null;
                    if (!$critId) {
                        continue;
                    }
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'grupo_id' => $grupo->id,
                        'criterio_id' => $critId,
                        'estudiante_id' => null,
                        'puntaje' => $score,
                    ]);
                }

                foreach ($request->overrides ?? [] as $ov) {
                    $estId = isset($ov['studentId']) ? ($studentIdsByUuid[$ov['studentId']] ?? null) : null;
                    if (!$estId) {
                        continue;
                    }
                    foreach ($ov['criterionScores'] ?? [] as $critUuid => $score) {
                        $critId = $criteriaMap[$critUuid] ?? null;
                        if (!$critId) {
                            continue;
                        }
                        ActivityGroupOverride::create([
                            'uuid' => Str::uuid()->toString(),
                            'grupo_id' => $grupo->id,
                            'estudiante_id' => $estId,
                            'criterio_id' => $critId,
                            'puntaje' => $score,
                        ]);
                    }
                }
            }
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateActivityGroup($courseUuid, $unitUuid, $activityUuid, $groupUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $activity = $this->findActivityAny($unidad, $activityUuid);
        $grupo = ActivityGroup::where('uuid', $groupUuid)->where('activity_id', $activity->id)->firstOrFail();

        DB::beginTransaction();
        try {
            $data = [];
            if ($request->has('name')) { $data['nombre'] = $request->name; }
            $data['updated_at'] = $request->input('updated_at') ?? $now;
            $data['sync_status'] = 'synced';
            $data['device_id'] = $request->input('device_id') ?? null;
            $data['deleted_at'] = $request->input('deleted_at') ?? null;
            if (!empty($data)) { $grupo->update($data); }

            if ($request->has('studentIds')) {
                DB::table('app_activity_group_members')->where('grupo_id', $grupo->id)->delete();
                $studentIdsByUuid = $this->studentMap($curso);
                foreach ($request->studentIds as $estUuid) {
                    $estId = $studentIdsByUuid[$estUuid] ?? null;
                    if ($estId) {
                        DB::table('app_activity_group_members')->insert(['grupo_id' => $grupo->id, 'estudiante_id' => $estId]);
                    }
                }
            }

            ActivityScore::where('grupo_id', $grupo->id)->forceDelete();
            if (!$activity->uses_rubric) {
                if ($request->has('score')) {
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'grupo_id' => $grupo->id,
                        'criterio_id' => null,
                        'estudiante_id' => null,
                        'puntaje' => $request->input('score', 0),
                    ]);
                }
            } else {
                $criteriaMap = $activity->criteria->pluck('id', 'uuid');
                if ($request->has('criterionScores')) {
                    foreach ($request->criterionScores as $critUuid => $score) {
                        $critId = $criteriaMap[$critUuid] ?? null;
                        if (!$critId) {
                            continue;
                        }
                        ActivityScore::create([
                            'uuid' => Str::uuid()->toString(),
                            'activity_id' => $activity->id,
                            'grupo_id' => $grupo->id,
                            'criterio_id' => $critId,
                            'estudiante_id' => null,
                            'puntaje' => $score,
                        ]);
                    }
                }

                if ($request->has('overrides')) {
                    ActivityGroupOverride::where('grupo_id', $grupo->id)->forceDelete();
                    $studentIdsByUuid = $this->studentMap($curso);
                    foreach ($request->overrides as $ov) {
                        $estId = isset($ov['studentId']) ? ($studentIdsByUuid[$ov['studentId']] ?? null) : null;
                        if (!$estId) {
                            continue;
                        }
                        foreach ($ov['criterionScores'] ?? [] as $critUuid => $score) {
                            $critId = $criteriaMap[$critUuid] ?? null;
                            if (!$critId) {
                                continue;
                            }
                            ActivityGroupOverride::create([
                                'uuid' => Str::uuid()->toString(),
                                'grupo_id' => $grupo->id,
                                'estudiante_id' => $estId,
                                'criterio_id' => $critId,
                                'puntaje' => $score,
                            ]);
                        }
                    }
                }
            }
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyActivityGroup($courseUuid, $unitUuid, $activityUuid, $groupUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivityAny($unidad, $activityUuid);
        $grupo = ActivityGroup::where('uuid', $groupUuid)->where('activity_id', $activity->id)->first();
        if ($grupo) {
            // Limpieza en cascada: miembros, puntajes del grupo y overrides.
            DB::table('app_activity_group_members')->where('grupo_id', $grupo->id)->delete();
            ActivityGroupOverride::where('grupo_id', $grupo->id)->forceDelete();
            ActivityScore::where('grupo_id', $grupo->id)->forceDelete();
            $this->softDelete($grupo);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updateActivityOverride($courseUuid, $unitUuid, $activityUuid, $groupUuid, $studentId, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivityAny($unidad, $activityUuid);
        $grupo = ActivityGroup::where('uuid', $groupUuid)->where('activity_id', $activity->id)->firstOrFail();
        $est = Estudiante::where('uuid', $studentId)->where('curso_id', $curso->id)->firstOrFail();

        DB::beginTransaction();
        try {
            ActivityGroupOverride::where('grupo_id', $grupo->id)->where('estudiante_id', $est->id)->delete();

            $criteriaMap = $activity->criteria->pluck('id', 'uuid');
            foreach ($request->criterionScores ?? [] as $critUuid => $score) {
                $critId = $criteriaMap[$critUuid] ?? null;
                if (!$critId) {
                    continue;
                }
                ActivityGroupOverride::create([
                    'uuid' => Str::uuid()->toString(),
                    'grupo_id' => $grupo->id,
                    'estudiante_id' => $est->id,
                    'criterio_id' => $critId,
                    'puntaje' => $score,
                ]);
            }
            $activity->update(['updated_at' => $this->now()]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyActivityOverride($courseUuid, $unitUuid, $activityUuid, $groupUuid, $studentId)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $activity = $this->findActivityAny($unidad, $activityUuid);
        $grupo = ActivityGroup::where('uuid', $groupUuid)->where('activity_id', $activity->id)->firstOrFail();
        $est = Estudiante::where('uuid', $studentId)->where('curso_id', $curso->id)->firstOrFail();

        ActivityGroupOverride::where('grupo_id', $grupo->id)->where('estudiante_id', $est->id)->delete();
        $activity->update(['updated_at' => $this->now()]);
        $this->touchCourse($curso);
        return response()->json(null, 204);
    }

    // ===================== 4.4 TASKS (unificado) =====================

    public function storeTask($courseUuid, $unitUuid, Request $request)
    {
        return $this->storeActivity($courseUuid, $unitUuid, 'task', $request);
    }

    public function updateTask($courseUuid, $unitUuid, $taskUuid, Request $request)
    {
        return $this->updateActivity($courseUuid, $unitUuid, $taskUuid, 'task', $request);
    }

    public function destroyTask($courseUuid, $unitUuid, $taskUuid)
    {
        return $this->destroyActivity($courseUuid, $unitUuid, $taskUuid, 'task');
    }

    public function updateTaskScores($courseUuid, $unitUuid, $taskUuid, Request $request)
    {
        return $this->updateActivityScores($courseUuid, $unitUuid, $taskUuid, 'task', $request);
    }

    public function updateTaskCriteria($courseUuid, $unitUuid, $taskUuid, Request $request)
    {
        return $this->updateActivityCriteria($courseUuid, $unitUuid, $taskUuid, 'task', $request);
    }

    // ===================== 4.5 PRACTICES (unificado) =====================

    public function storePractice($courseUuid, $unitUuid, Request $request)
    {
        return $this->storeActivity($courseUuid, $unitUuid, 'practice', $request);
    }

    public function updatePractice($courseUuid, $unitUuid, $practiceUuid, Request $request)
    {
        return $this->updateActivity($courseUuid, $unitUuid, $practiceUuid, 'practice', $request);
    }

    public function destroyPractice($courseUuid, $unitUuid, $practiceUuid)
    {
        return $this->destroyActivity($courseUuid, $unitUuid, $practiceUuid, 'practice');
    }

    public function updatePracticeScores($courseUuid, $unitUuid, $practiceUuid, Request $request)
    {
        return $this->updateActivityScores($courseUuid, $unitUuid, $practiceUuid, 'practice', $request);
    }

    public function updatePracticeCriteria($courseUuid, $unitUuid, $practiceUuid, Request $request)
    {
        return $this->updateActivityCriteria($courseUuid, $unitUuid, $practiceUuid, 'practice', $request);
    }

    // ===================== 4.6 PARTICIPATION (unificado) =====================

    public function storeParticipation($courseUuid, $unitUuid, Request $request)
    {
        return $this->storeActivity($courseUuid, $unitUuid, 'participation', $request);
    }

    public function updateParticipation($courseUuid, $unitUuid, $itemUuid, Request $request)
    {
        return $this->updateActivity($courseUuid, $unitUuid, $itemUuid, 'participation', $request);
    }

    public function destroyParticipation($courseUuid, $unitUuid, $itemUuid)
    {
        return $this->destroyActivity($courseUuid, $unitUuid, $itemUuid, 'participation');
    }

    public function updateParticipationScores($courseUuid, $unitUuid, $itemUuid, Request $request)
    {
        return $this->updateActivityScores($courseUuid, $unitUuid, $itemUuid, 'participation', $request);
    }

    public function updateParticipationCriteria($courseUuid, $unitUuid, $itemUuid, Request $request)
    {
        return $this->updateActivityCriteria($courseUuid, $unitUuid, $itemUuid, 'participation', $request);
    }

    // ===================== 4.7 GROUP WORKS (unificado) =====================

    public function storeGroupWork($courseUuid, $unitUuid, Request $request)
    {
        return $this->storeActivity($courseUuid, $unitUuid, 'group_work', $request);
    }

    public function updateGroupWork($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        return $this->updateActivity($courseUuid, $unitUuid, $gwUuid, 'group_work', $request);
    }

    public function destroyGroupWork($courseUuid, $unitUuid, $gwUuid)
    {
        return $this->destroyActivity($courseUuid, $unitUuid, $gwUuid, 'group_work');
    }

    public function updateGroupWorkCriteria($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        return $this->updateActivityCriteria($courseUuid, $unitUuid, $gwUuid, 'group_work', $request);
    }

    // Wrappers de compatibilidad (trabajo grupal => actividad genérica)
    public function storeGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        return $this->storeActivityGroup($courseUuid, $unitUuid, $gwUuid, $request);
    }

    public function updateGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid, Request $request)
    {
        return $this->updateActivityGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid, $request);
    }

    public function destroyGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid)
    {
        return $this->destroyActivityGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid);
    }

    public function updateGroupWorkOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId, Request $request)
    {
        return $this->updateActivityOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId, $request);
    }

    public function destroyGroupWorkOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId)
    {
        return $this->destroyActivityOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId);
    }

    // ===================== 4.8 PROJECTS (múltiples por unidad, unificado) =====================

    public function storeProject($courseUuid, $unitUuid, Request $request)
    {
        return $this->storeActivity($courseUuid, $unitUuid, 'project', $request);
    }

    public function updateProject($courseUuid, $unitUuid, $projectUuid, Request $request)
    {
        return $this->updateActivity($courseUuid, $unitUuid, $projectUuid, 'project', $request);
    }

    public function destroyProject($courseUuid, $unitUuid, $projectUuid)
    {
        return $this->destroyActivity($courseUuid, $unitUuid, $projectUuid, 'project');
    }

    public function updateProjectScores($courseUuid, $unitUuid, $projectUuid, Request $request)
    {
        return $this->updateActivityScores($courseUuid, $unitUuid, $projectUuid, 'project', $request);
    }

    public function updateProjectCriteria($courseUuid, $unitUuid, $projectUuid, Request $request)
    {
        return $this->updateActivityCriteria($courseUuid, $unitUuid, $projectUuid, 'project', $request);
    }

    // ===================== 4.9 SETTINGS =====================

    public function updateSettings($courseUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();

        $curso->puntaje_max_tarea = $request->input('maxTaskScore', $curso->puntaje_max_tarea);
        $curso->puntaje_max_practica = $request->input('maxPracticeScore', $curso->puntaje_max_practica);
        $curso->puntaje_max_participacion = $request->input('maxParticipation', $curso->puntaje_max_participacion);
        $curso->puntaje_max_trabajo_grupal = $request->input('maxGroupWorkScore', $curso->puntaje_max_trabajo_grupal);
        $curso->puntaje_max_proyecto = $request->input('maxProjectScore', $curso->puntaje_max_proyecto);
        $curso->peso_asistencia = $request->input('pctAttendance', $curso->peso_asistencia);
        $curso->peso_tareas = $request->input('pctTasks', $curso->peso_tareas);
        $curso->peso_practicas = $request->input('pctPractices', $curso->peso_practicas);
        $curso->peso_participacion = $request->input('pctParticipation', $curso->peso_participacion);
        $curso->peso_proyecto = $request->input('pctProject', $curso->peso_proyecto);
        $curso->updated_at = $request->input('updated_at') ?? $now;
        $curso->sync_status = 'synced';
        $curso->device_id = $request->input('device_id') ?? $curso->device_id;
        $curso->save();

        return response()->json(['message' => 'ok'], 200);
    }

    public function updateSelectedUnitIndex($courseUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $now = $this->now();
        $request->validate(['selectedUnitIndex' => 'required|integer']);
        $curso->update([
            'indice_unidad_seleccionada' => $request->selectedUnitIndex,
            'updated_at' => $request->input('updated_at') ?? $now,
            'sync_status' => 'synced',
            'device_id' => $request->input('device_id') ?? $curso->device_id,
        ]);
        return response()->json(['message' => 'ok'], 200);
    }
}