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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseSyncController extends Controller
{
    private function now()
    {
        return now()->format('Y-m-d H:i:s');
    }

    /**
     * Eager-loads compartidos para serializar un curso completo.
     * Evita el N+1 del formateador (formatCourse accede lazy a cada relación).
     */
    private function relationLoads(): array
    {
        return [
            'estudiantes',
            'unidades.sesiones.registrosAsistencia.estudiante',
            'unidades.activities.criteria',
            'unidades.activities.groups.estudiantes',
            'unidades.activities.groups.scores.criterio',
            'unidades.activities.groups.overrides.estudiante',
            'unidades.activities.groups.overrides.criterio',
            'unidades.activities.scores.estudiante',
            'unidades.activities.scores.criterio',
        ];
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

    private function attendanceFromDb($dbValue)
    {
        return match ($dbValue) {
            'presente' => 'present',
            'ausente' => 'absent',
            'tardanza' => 'late',
            'justificado' => 'justified',
            default => 'present',
        };
    }

    private function formatTimestamp($value)
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value)) {
            // Si ya está en formato correcto, devolverlo
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                return $value;
            }
            // Intentar parsear y reformatear
            $parsed = \Carbon\Carbon::parse($value);
            return $parsed->format('Y-m-d H:i:s');
        }
        return $value;
    }

    public function index(Request $request)
    {
        $query = Curso::where('usuario_id', auth()->id())
            ->with($this->relationLoads());

        if ($request->has('since')) {
            $since = $request->input('since');
            $since = str_replace(['T', 'Z'], [' ', ''], $since);
            $since = substr($since, 0, 19);
            $query->where('updated_at', '>', $since);
        }
        $query->whereNull('deleted_at');

        $cursos = $query->get();

        $result = $cursos->map(function ($curso) {
            return $this->formatCourse($curso);
        });

        return response()->json(['data' => $result]);
    }

    public function show($id)
    {
        $curso = Curso::where('uuid', $id)
            ->where('usuario_id', auth()->id())
            ->whereNull('deleted_at')
            ->with($this->relationLoads())
            ->firstOrFail();

        return response()->json(['data' => $this->formatCourse($curso)]);
    }

    public function update(Request $request, $id)
    {
        $curso = Curso::where('uuid', $id)->where('usuario_id', auth()->id())->whereNull('deleted_at')->firstOrFail();
        $now = $this->now();

        if ($request->has('name')) {
            $curso->nombre = $request->name;
        }
        if ($request->has('description')) {
            $curso->descripcion = $request->description;
        }
        if ($request->has('selectedUnitIndex')) {
            $curso->indice_unidad_seleccionada = $request->selectedUnitIndex;
        }
        if ($request->has('settings')) {
            $curso->puntaje_max_tarea = $request->input('settings.maxTaskScore', $curso->puntaje_max_tarea);
            $curso->puntaje_max_practica = $request->input('settings.maxPracticeScore', $curso->puntaje_max_practica);
            $curso->puntaje_max_participacion = $request->input('settings.maxParticipation', $curso->puntaje_max_participacion);
            $curso->puntaje_max_trabajo_grupal = $request->input('settings.maxGroupWorkScore', $curso->puntaje_max_trabajo_grupal);
            $curso->puntaje_max_proyecto = $request->input('settings.maxProjectScore', $curso->puntaje_max_proyecto);
            $curso->peso_asistencia = $request->input('settings.pctAttendance', $curso->peso_asistencia);
            $curso->peso_tareas = $request->input('settings.pctTasks', $curso->peso_tareas);
            $curso->peso_practicas = $request->input('settings.pctPractices', $curso->peso_practicas);
            $curso->peso_participacion = $request->input('settings.pctParticipation', $curso->peso_participacion);
            $curso->peso_proyecto = $request->input('settings.pctProject', $curso->peso_proyecto);
        }
        $curso->updated_at = $now;
        $curso->sync_status = 'synced';
        $curso->save();

        // Eager-load para no disparar miles de consultas en formatCourse.
        $curso->load($this->relationLoads());

        return response()->json(['data' => $this->formatCourse($curso)]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string',
            'name' => 'required|string',
        ]);

        $uuid = $request->id;
        $now = $this->now();

        $curso = Curso::where('uuid', $uuid)->where('usuario_id', auth()->id())->first();
        $isNew = !$curso;

        if ($isNew) {
            $curso = new Curso();
            $curso->uuid = $uuid;
            $curso->usuario_id = auth()->id();
            $curso->created_at = $request->input('created_at') ?? $now;
        } else {
            $curso->created_at = $curso->created_at;
        }

        $curso->nombre = $request->name;
        $curso->descripcion = $request->description ?? null;
        $curso->indice_unidad_seleccionada = $request->selectedUnitIndex ?? 0;
        $curso->puntaje_max_tarea = $request->input('settings.maxTaskScore', 20);
        $curso->puntaje_max_practica = $request->input('settings.maxPracticeScore', 20);
        $curso->puntaje_max_participacion = $request->input('settings.maxParticipation', 20);
        $curso->puntaje_max_trabajo_grupal = $request->input('settings.maxGroupWorkScore', 20);
        $curso->puntaje_max_proyecto = $request->input('settings.maxProjectScore', 20);
        $curso->peso_asistencia = $request->input('settings.pctAttendance', 20);
        $curso->peso_tareas = $request->input('settings.pctTasks', 20);
        $curso->peso_practicas = $request->input('settings.pctPractices', 20);
        $curso->peso_participacion = $request->input('settings.pctParticipation', 20);
        $curso->peso_proyecto = $request->input('settings.pctProject', 20);
        $curso->updated_at = $request->input('updated_at') ?? $now;
        $curso->deleted_at = $request->input('deleted_at');
        $curso->sync_status = 'synced';
        $curso->device_id = $request->input('device_id');
        $curso->save();

        // Eager-load para no disparar miles de consultas en formatCourse.
        $curso->load($this->relationLoads());

        return response()->json(['data' => $this->formatCourse($curso)]);
    }

    public function sync(Request $request)
    {
        $request->validate([
            'courses' => 'required|array',
            'courses.*.id' => 'required|string',
            'courses.*.name' => 'required|string',
        ]);

        $userId = auth()->id();

        DB::beginTransaction();
        try {
            $sentUuids = collect($request->courses)->pluck('id')->toArray();
            Curso::where('usuario_id', $userId)->whereNotIn('uuid', $sentUuids)->forceDelete();

            foreach ($request->courses as $courseData) {
                $this->upsertCourse($userId, $courseData);
            }

            DB::commit();
            return response()->json(['message' => 'Cursos sincronizados']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al sincronizar', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Restaura por completo UN curso a partir del snapshot local del cliente.
     * Se usa cuando la cola del dispositivo encuentra operaciones de un curso
     * que no existe (o está incompleto) en el servidor: p. ej. la BD del
     * servidor se restableció, o una unidad/sesión nunca llegó a crearse.
     *
     * Hace upsert total del curso (si ya existe se reemplazan todos sus hijos;
     * si no, se crea). A diferencia de POST /courses/sync, NO borra los demás
     * cursos del usuario.
     */
    public function restoreCourse($courseUuid, Request $request)
    {
        $request->validate(['name' => 'required|string']);

        $existing = Curso::where('uuid', $courseUuid)->first();
        if ($existing && (int) $existing->usuario_id !== (int) auth()->id()) {
            return response()->json(['error' => 'El curso pertenece a otro usuario'], 403);
        }

        $data = $request->all();
        $data['id'] = $courseUuid;

        DB::beginTransaction();
        try {
            $this->upsertCourse(auth()->id(), $data);
            DB::commit();
            \Illuminate\Support\Facades\Log::info("Curso restaurado: {$courseUuid}");
            return response()->json(['message' => 'ok']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al restaurar', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $now = $this->now();
        $curso = Curso::where('uuid', $id)->where('usuario_id', auth()->id())->first();
        if ($curso) {
            $curso->update([
                'deleted_at' => $now,
                'updated_at' => $now,
                'sync_status' => 'synced',
            ]);

            $curso->estudiantes()->update([
                'deleted_at' => $now,
                'updated_at' => $now,
                'sync_status' => 'synced',
            ]);
            $studentIds = $curso->estudiantes()->pluck('id');

            $unidades = $curso->unidades()->get();
            foreach ($unidades as $unidad) {
                // Soft-delete de asistencia (hijos de las sesiones).
                $sessionIds = $unidad->sesiones()->pluck('id');
                if ($sessionIds->isNotEmpty()) {
                    RegistroAsistencia::whereIn('sesion_id', $sessionIds)->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $unidad->sesiones()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);

                // Soft-delete de criterios, puntajes y grupos de las actividades.
                $activityIds = $unidad->activities()->pluck('id');
                if ($activityIds->isNotEmpty()) {
                    $groupIds = ActivityGroup::whereIn('activity_id', $activityIds)->pluck('id');
                    if ($groupIds->isNotEmpty()) {
                        // La tabla de miembros no tiene deleted_at: se eliminan
                        // físicamente (relación pura de muchos-a-muchos).
                        DB::table('app_activity_group_members')->whereIn('grupo_id', $groupIds)->delete();
                        ActivityGroupOverride::whereIn('grupo_id', $groupIds)->update([
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    ActivityScore::whereIn('activity_id', $activityIds)->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
                    ActivityGroup::whereIn('activity_id', $activityIds)->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
                    ActivityCriterion::whereIn('activity_id', $activityIds)->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $unidad->activities()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);

                $unidad->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
            }

            // Alertas asociadas al curso o a sus estudiantes.
            Alerta::where('curso_id', $curso->id)
                ->orWhereIn('estudiante_id', $studentIds)
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        }
        return response()->json(['ok' => true]);
    }

    private function upsertCourse($userId, $data)
    {
        $uuid = $data['id'];
        $now = $this->now();

        $curso = Curso::where('uuid', $uuid)->first();
        $isNew = !$curso;

        if ($isNew) {
            $curso = new Curso();
            $curso->uuid = $uuid;
            $curso->usuario_id = $userId;
            $curso->created_at = $data['created_at'] ?? $now;
        } else {
            $curso->created_at = $curso->created_at;
        }

        $curso->nombre = $data['name'];
        $curso->descripcion = $data['description'] ?? null;
        $curso->indice_unidad_seleccionada = $data['selectedUnitIndex'] ?? 0;
        $curso->puntaje_max_tarea = $data['settings']['maxTaskScore'] ?? 20;
        $curso->puntaje_max_practica = $data['settings']['maxPracticeScore'] ?? 20;
        $curso->puntaje_max_participacion = $data['settings']['maxParticipation'] ?? 20;
        $curso->puntaje_max_trabajo_grupal = $data['settings']['maxGroupWorkScore'] ?? 20;
        $curso->puntaje_max_proyecto = $data['settings']['maxProjectScore'] ?? 20;
        $curso->peso_asistencia = $data['settings']['pctAttendance'] ?? 20;
        $curso->peso_tareas = $data['settings']['pctTasks'] ?? 20;
        $curso->peso_practicas = $data['settings']['pctPractices'] ?? 20;
        $curso->peso_participacion = $data['settings']['pctParticipation'] ?? 20;
        $curso->peso_proyecto = $data['settings']['pctProject'] ?? 20;
        $curso->updated_at = $data['updated_at'] ?? $now;
        $curso->deleted_at = $data['deleted_at'] ?? null;
        $curso->sync_status = 'synced';
        $curso->device_id = $data['device_id'] ?? null;
        $curso->save();

        if (!$isNew) {
            // Eliminación física en cascada de TODO lo relacionado al curso:
            // antes solo se borraban estudiantes y unidades, dejando huérfanos
            // (asistencias, criterios, puntajes, grupos, miembros, overrides y
            // alertas de cursos/estudiantes viejos) acumulándose para siempre.
            $this->hardDeleteCourseChildren($curso);
        }

        $this->insertCourseChildren($curso, $data);

        return $curso;
    }

    /**
     * Elimina físicamente, en cascada, todos los datos de un curso que se va a
     * recrear por completa en un sync (POST /courses/sync).
     */
    private function hardDeleteCourseChildren(Curso $curso)
    {
        $unitIds = $curso->unidades()->pluck('id');

        if ($unitIds->isNotEmpty()) {
            $sessionIds = Sesion::whereIn('unidad_id', $unitIds)->pluck('id');
            if ($sessionIds->isNotEmpty()) {
                RegistroAsistencia::whereIn('sesion_id', $sessionIds)->forceDelete();
            }

            $activityIds = Activity::whereIn('unidad_id', $unitIds)->pluck('id');
            if ($activityIds->isNotEmpty()) {
                $groupIds = ActivityGroup::whereIn('activity_id', $activityIds)->pluck('id');
                if ($groupIds->isNotEmpty()) {
                    DB::table('app_activity_group_members')->whereIn('grupo_id', $groupIds)->delete();
                    ActivityGroupOverride::whereIn('grupo_id', $groupIds)->forceDelete();
                }
                ActivityScore::whereIn('activity_id', $activityIds)->forceDelete();
                ActivityCriterion::whereIn('activity_id', $activityIds)->forceDelete();
                ActivityGroup::whereIn('activity_id', $activityIds)->forceDelete();
            }

            Activity::whereIn('unidad_id', $unitIds)->forceDelete();
            Sesion::whereIn('unidad_id', $unitIds)->forceDelete();
        }

        Unidad::where('curso_id', $curso->id)->forceDelete();

        $studentIds = Estudiante::where('curso_id', $curso->id)->pluck('id');
        if ($studentIds->isNotEmpty()) {
            Alerta::whereIn('estudiante_id', $studentIds)->forceDelete();
        }
        Alerta::where('curso_id', $curso->id)->forceDelete();
        Estudiante::where('curso_id', $curso->id)->forceDelete();
    }

    private function insertCourseChildren($curso, $data)
    {
        $now = $this->now();

        foreach ($data['students'] ?? [] as $s) {
            Estudiante::create([
                'curso_id' => $curso->id,
                'uuid' => $s['id'],
                'nombre' => $s['name'],
                'notas' => $s['notes'] ?? null,
                'created_at' => $s['created_at'] ?? $now,
                'updated_at' => $s['updated_at'] ?? $now,
                'deleted_at' => $s['deleted_at'] ?? null,
                'sync_status' => 'synced',
                'device_id' => $s['device_id'] ?? null,
            ]);
        }

        // Mapa uuid→id de los estudiantes del curso: evita 1 SELECT por fila
        // al insertar asistencias, puntajes, miembros y overrides.
        $studentIdsByUuid = Estudiante::where('curso_id', $curso->id)
            ->pluck('id', 'uuid')
            ->all();

        foreach ($data['units'] ?? [] as $uData) {
            $unidad = Unidad::create([
                'curso_id' => $curso->id,
                'uuid' => $uData['id'],
                'nombre' => $uData['name'],
                'orden' => 0,
                'created_at' => $uData['created_at'] ?? $now,
                'updated_at' => $uData['updated_at'] ?? $now,
                'deleted_at' => $uData['deleted_at'] ?? null,
                'sync_status' => 'synced',
                'device_id' => $uData['device_id'] ?? null,
            ]);

            foreach ($uData['sessions'] ?? [] as $sesData) {
                $sesion = Sesion::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $sesData['id'],
                    'fecha' => $sesData['date'],
                    'tema' => $sesData['topic'] ?? null,
                    'created_at' => $sesData['created_at'] ?? $now,
                    'updated_at' => $sesData['updated_at'] ?? $now,
                    'deleted_at' => $sesData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $sesData['device_id'] ?? null,
                ]);

                foreach ($sesData['records'] ?? [] as $recData) {
                    $estId = $studentIdsByUuid[$recData['studentId']] ?? null;
                    if ($estId) {
                        RegistroAsistencia::create([
                            'uuid' => Str::uuid()->toString(),
                            'sesion_id' => $sesion->id,
                            'estudiante_id' => $estId,
                            'asistencia' => $this->attendanceToDb($recData['attendance'] ?? 'present'),
                            'observaciones' => $recData['observations'] ?? null,
                        ]);
                    }
                }
            }

            $this->insertActivity($curso, $unidad, $uData['tasks'] ?? [], 'task', $now, true, $studentIdsByUuid);
            $this->insertActivity($curso, $unidad, $uData['practices'] ?? [], 'practice', $now, true, $studentIdsByUuid);
            $this->insertActivity($curso, $unidad, $uData['participationItems'] ?? [], 'participation', $now, true, $studentIdsByUuid);
            $this->insertActivity($curso, $unidad, $uData['groupWorks'] ?? [], 'group_work', $now, true, $studentIdsByUuid);

            // Proyectos: el contrato nuevo envía 'projects[]'; se conserva 'project' (single) por compatibilidad.
            $projects = $uData['projects'] ?? [];
            if (empty($projects) && isset($uData['project']) && $uData['project'] !== null) {
                $projects = [$uData['project']];
            }
            $this->insertActivity($curso, $unidad, $projects, 'project', $now, true, $studentIdsByUuid);
        }
    }

    /**
     * Inserta una lista de actividades (de cualquier tipo) con su rúbrica,
     * puntajes individuales y grupos.
     */
    private function insertActivity($curso, $unidad, $items, $type, $now, $isList, $studentIdsByUuid = [])
    {
        $list = $isList ? $items : [$items];
        foreach ($list as $itemData) {
            $this->createActivityWithChildren($curso, $unidad, $itemData, $type, $now, $studentIdsByUuid);
        }
    }

    private function createActivityWithChildren($curso, $unidad, $itemData, $type, $now, $studentIdsByUuid = [])
    {
        $defaults = match ($type) {
            'group_work' => ['is_group_based' => true, 'uses_rubric' => true],
            'project' => ['is_group_based' => false, 'uses_rubric' => true],
            default => ['is_group_based' => false, 'uses_rubric' => false],
        };

        $activity = Activity::create([
            'unidad_id' => $unidad->id,
            'uuid' => $itemData['id'] ?? Str::uuid()->toString(),
            'type' => $type,
            'nombre' => $itemData['name'] ?? ucfirst($type),
            'fecha' => $itemData['date'] ?? $now,
            'is_group_based' => $itemData['isGroupBased'] ?? $defaults['is_group_based'],
            'uses_rubric' => $itemData['usesRubric'] ?? $defaults['uses_rubric'],
            'created_at' => $itemData['created_at'] ?? $now,
            'updated_at' => $itemData['updated_at'] ?? $now,
            'deleted_at' => $itemData['deleted_at'] ?? null,
            'sync_status' => 'synced',
            'device_id' => $itemData['device_id'] ?? null,
        ]);

        $criteriaMap = [];
        foreach ($itemData['criteria'] ?? [] as $cData) {
            $crit = ActivityCriterion::create([
                'activity_id' => $activity->id,
                'uuid' => $cData['id'] ?? Str::uuid()->toString(),
                'nombre' => $cData['name'] ?? 'Criterio',
                'puntaje_maximo' => $cData['maxScore'] ?? 5,
                'created_at' => $cData['created_at'] ?? $now,
                'updated_at' => $cData['updated_at'] ?? $now,
                'deleted_at' => $cData['deleted_at'] ?? null,
                'sync_status' => 'synced',
                'device_id' => $cData['device_id'] ?? null,
            ]);
            $criteriaMap[$crit->uuid] = $crit->id;
        }

        // Puntajes individuales
        foreach ($itemData['scores'] ?? [] as $scData) {
            $estId = $studentIdsByUuid[$scData['studentId']] ?? null;
            if (!$estId) {
                continue;
            }
            if ($activity->uses_rubric) {
                foreach ($scData['criterionScores'] ?? [] as $critUuid => $score) {
                    if (!isset($criteriaMap[$critUuid])) {
                        continue;
                    }
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'estudiante_id' => $estId,
                        'criterio_id' => $criteriaMap[$critUuid],
                        'grupo_id' => null,
                        'puntaje' => $score,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'sync_status' => 'synced',
                    ]);
                }
            } else {
                ActivityScore::create([
                    'uuid' => Str::uuid()->toString(),
                    'activity_id' => $activity->id,
                    'estudiante_id' => $estId,
                    'criterio_id' => null,
                    'grupo_id' => null,
                    'puntaje' => $scData['score'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
            }
        }

        // Grupos
        foreach ($itemData['groups'] ?? [] as $gData) {
            $grupo = ActivityGroup::create([
                'activity_id' => $activity->id,
                'uuid' => $gData['id'] ?? Str::uuid()->toString(),
                'nombre' => $gData['name'] ?? 'Grupo',
                'created_at' => $gData['created_at'] ?? $now,
                'updated_at' => $gData['updated_at'] ?? $now,
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
                foreach ($gData['criterionScores'] ?? [] as $critUuid => $score) {
                    if (!isset($criteriaMap[$critUuid])) {
                        continue;
                    }
                    ActivityScore::create([
                        'uuid' => Str::uuid()->toString(),
                        'activity_id' => $activity->id,
                        'grupo_id' => $grupo->id,
                        'criterio_id' => $criteriaMap[$critUuid],
                        'estudiante_id' => null,
                        'puntaje' => $score,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'sync_status' => 'synced',
                    ]);
                }

                foreach ($gData['overrides'] ?? [] as $ovData) {
                    $estId = $studentIdsByUuid[$ovData['studentId']] ?? null;
                    if (!$estId) {
                        continue;
                    }
                    foreach ($ovData['criterionScores'] ?? [] as $critUuid => $score) {
                        if (!isset($criteriaMap[$critUuid])) {
                            continue;
                        }
                        ActivityGroupOverride::create([
                            'uuid' => Str::uuid()->toString(),
                            'grupo_id' => $grupo->id,
                            'estudiante_id' => $estId,
                            'criterio_id' => $criteriaMap[$critUuid],
                            'puntaje' => $score,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'sync_status' => 'synced',
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
                    'created_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
            }
        }

        return $activity;
    }

    // ===================== FORMATTERS =====================

    private function formatCourse($curso)
    {
        $course = [
            'id' => $curso->uuid ?? (string) $curso->id,
            'name' => $curso->nombre,
        ];

        if ($curso->descripcion !== null) {
            $course['description'] = $curso->descripcion;
        }

        $course['created_at'] = $this->formatTimestamp($curso->created_at);
        $course['updated_at'] = $this->formatTimestamp($curso->updated_at);

        if ($curso->deleted_at !== null) {
            $course['deleted_at'] = $this->formatTimestamp($curso->deleted_at);
        }

        $course['sync_status'] = $curso->sync_status ?? 'synced';

        if ($curso->device_id !== null) {
            $course['device_id'] = $curso->device_id;
        }

        $course['settings'] = [
            'maxTaskScore' => (float) ($curso->puntaje_max_tarea ?? 20),
            'maxPracticeScore' => (float) ($curso->puntaje_max_practica ?? 20),
            'maxParticipation' => (float) ($curso->puntaje_max_participacion ?? 20),
            'maxGroupWorkScore' => (float) ($curso->puntaje_max_trabajo_grupal ?? 20),
            'maxProjectScore' => (float) ($curso->puntaje_max_proyecto ?? 20),
            'pctAttendance' => (float) ($curso->peso_asistencia ?? 20),
            'pctTasks' => (float) ($curso->peso_tareas ?? 20),
            'pctPractices' => (float) ($curso->peso_practicas ?? 20),
            'pctParticipation' => (float) ($curso->peso_participacion ?? 20),
            'pctProject' => (float) ($curso->peso_proyecto ?? 20),
        ];

        $course['students'] = $curso->estudiantes->map(function ($est) {
            return $this->formatStudent($est);
        })->values()->toArray();

        $course['units'] = $curso->unidades->map(function ($unidad) {
            return $this->formatUnit($unidad);
        })->values()->toArray();

        $course['selectedUnitIndex'] = $curso->indice_unidad_seleccionada ?? 0;

        return $course;
    }

    private function formatStudent($est)
    {
        $s = [
            'id' => $est->uuid ?? (string) $est->id,
            'name' => $est->nombre,
        ];
        if ($est->notas !== null) {
            $s['notes'] = $est->notas;
        }
        $s['created_at'] = $this->formatTimestamp($est->created_at);
        $s['updated_at'] = $this->formatTimestamp($est->updated_at);
        if ($est->deleted_at !== null) {
            $s['deleted_at'] = $this->formatTimestamp($est->deleted_at);
        }
        $s['sync_status'] = $est->sync_status ?? 'synced';
        if ($est->device_id !== null) {
            $s['device_id'] = $est->device_id;
        }
        return $s;
    }

    private function formatUnit($unidad)
    {
        $unit = [
            'id' => $unidad->uuid ?? (string) $unidad->id,
            'name' => $unidad->nombre,
            'created_at' => $this->formatTimestamp($unidad->created_at),
            'updated_at' => $this->formatTimestamp($unidad->updated_at),
        ];
        if ($unidad->deleted_at !== null) {
            $unit['deleted_at'] = $this->formatTimestamp($unidad->deleted_at);
        }
        $unit['sync_status'] = $unidad->sync_status ?? 'synced';
        if ($unidad->device_id !== null) {
            $unit['device_id'] = $unidad->device_id;
        }

        $unit['sessions'] = $unidad->sesiones->map(function ($sesion) {
            return $this->formatSession($sesion);
        })->values()->toArray();

        $activities = $unidad->activities;

        $unit['tasks'] = $activities->where('type', 'task')->map(function ($a) {
            return $this->formatActivityItem($a);
        })->values()->toArray();

        $unit['practices'] = $activities->where('type', 'practice')->map(function ($a) {
            return $this->formatActivityItem($a);
        })->values()->toArray();

        $unit['participationItems'] = $activities->where('type', 'participation')->map(function ($a) {
            return $this->formatActivityItem($a);
        })->values()->toArray();

        $unit['groupWorks'] = $activities->where('type', 'group_work')->map(function ($a) {
            return $this->formatActivityItem($a);
        })->values()->toArray();

        // Proyectos: lista (múltiples por unidad) + `project` legacy (primer proyecto o null)
        $unit['projects'] = $activities->where('type', 'project')->map(function ($a) {
            return $this->formatActivityItem($a);
        })->values()->toArray();

        return $unit;
    }

    private function formatSession($sesion)
    {
        $s = [
            'id' => $sesion->uuid ?? (string) $sesion->id,
            'date' => $sesion->fecha instanceof \Carbon\Carbon
                ? $sesion->fecha->format('Y-m-d')
                : $sesion->fecha,
        ];
        if ($sesion->tema !== null) {
            $s['topic'] = $sesion->tema;
        }
        $s['created_at'] = $this->formatTimestamp($sesion->created_at);
        $s['updated_at'] = $this->formatTimestamp($sesion->updated_at);
        if ($sesion->deleted_at !== null) {
            $s['deleted_at'] = $this->formatTimestamp($sesion->deleted_at);
        }
        $s['sync_status'] = $sesion->sync_status ?? 'synced';
        if ($sesion->device_id !== null) {
            $s['device_id'] = $sesion->device_id;
        }
        $s['records'] = $sesion->registrosAsistencia->map(function ($rec) {
            $r = [
                'studentId' => $rec->estudiante->uuid ?? (string) $rec->estudiante_id,
                'studentName' => $rec->estudiante->nombre ?? '',
                'attendance' => $this->attendanceFromDb($rec->asistencia),
            ];
            if ($rec->observaciones !== null) {
                $r['observations'] = $rec->observaciones;
            }
            return $r;
        })->values()->toArray();

        return $s;
    }

    private function formatActivityBase($activity)
    {
        return [
            'id' => $activity->uuid ?? (string) $activity->id,
            'name' => $activity->nombre,
            'date' => $activity->fecha instanceof \Carbon\Carbon
                ? $activity->fecha->format('Y-m-d')
                : $activity->fecha,
            'created_at' => $this->formatTimestamp($activity->created_at),
            'updated_at' => $this->formatTimestamp($activity->updated_at),
            'deleted_at' => $this->formatTimestamp($activity->deleted_at),
            'sync_status' => $activity->sync_status ?? 'synced',
            'device_id' => $activity->device_id ?? null,
        ];
    }

    /**
     * Formatea una actividad de cualquier tipo con el contrato unificado:
     * banderas (isGroupBased/usesRubric), rúbrica (criteria), puntajes
     * individuales (scores) y grupos.
     */
    private function formatActivityItem($activity)
    {
        $item = $this->formatActivityBase($activity);
        $item['isGroupBased'] = (bool) $activity->is_group_based;
        $item['usesRubric'] = (bool) $activity->uses_rubric;

        $item['criteria'] = $activity->criteria->map(function ($c) {
            return [
                'id' => $c->uuid ?? (string) $c->id,
                'name' => $c->nombre,
                'maxScore' => (float) ($c->puntaje_maximo ?? 5),
            ];
        })->values()->toArray();

        // Puntajes individuales: con rúbrica se agrupan por criterio, sin rúbrica nota simple
        $scores = [];
        if ((bool) $activity->uses_rubric) {
            foreach ($activity->scores->whereNull('grupo_id')->groupBy('estudiante_id') as $estId => $cals) {
                $est = $cals->first()->estudiante;
                $criterionScores = [];
                foreach ($cals as $cal) {
                    $critUuid = $cal->criterio->uuid ?? (string) $cal->criterio_id;
                    $criterionScores[$critUuid] = (float) $cal->puntaje;
                }
                $scores[] = [
                    'studentId' => $est->uuid ?? (string) $estId,
                    'criterionScores' => $criterionScores,
                ];
            }
        } else {
            $scores = $activity->scores->whereNull('grupo_id')->whereNull('criterio_id')->map(function ($cal) {
                return [
                    'studentId' => $cal->estudiante->uuid ?? (string) $cal->estudiante_id,
                    'score' => (float) $cal->puntaje,
                ];
            })->values()->toArray();
        }
        $item['scores'] = $scores;

        // Grupos (con score simple o criterionScores + overrides según la rúbrica)
        $item['groups'] = $activity->groups->map(function ($g) use ($activity) {
            $group = [
                'id' => $g->uuid ?? (string) $g->id,
                'name' => $g->nombre,
                'studentIds' => $g->estudiantes->map(function ($e) {
                    return $e->uuid ?? (string) $e->id;
                })->values()->toArray(),
                'created_at' => $this->formatTimestamp($g->created_at),
                'updated_at' => $this->formatTimestamp($g->updated_at),
                'deleted_at' => $this->formatTimestamp($g->deleted_at),
                'sync_status' => $g->sync_status ?? 'synced',
                'device_id' => $g->device_id ?? null,
            ];

            if ((bool) $activity->uses_rubric) {
                $criterionScores = [];
                foreach ($g->scores as $pc) {
                    $critUuid = $pc->criterio->uuid ?? (string) $pc->criterio_id;
                    $criterionScores[$critUuid] = (float) $pc->puntaje;
                }

                $overrides = [];
                foreach ($g->overrides->groupBy('estudiante_id') as $estId => $ajustes) {
                    $est = $ajustes->first()->estudiante;
                    $ovScores = [];
                    foreach ($ajustes as $aj) {
                        $critUuid = $aj->criterio->uuid ?? (string) $aj->criterio_id;
                        $ovScores[$critUuid] = (float) $aj->puntaje;
                    }
                    $overrides[] = [
                        'studentId' => $est->uuid ?? (string) $estId,
                        'criterionScores' => $ovScores,
                    ];
                }

                $group['criterionScores'] = $criterionScores;
                $group['overrides'] = $overrides;
            } else {
                $groupScore = $g->scores->first();
                $group['score'] = $groupScore ? (float) $groupScore->puntaje : null;
            }

            return $group;
        })->values()->toArray();

        return $item;
    }
}
