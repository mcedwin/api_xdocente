<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Unidad;
use App\Models\Sesion;
use App\Models\RegistroAsistencia;
use App\Models\Tarea;
use App\Models\CalificacionTarea;
use App\Models\Practica;
use App\Models\CalificacionPractica;
use App\Models\ItemParticipacion;
use App\Models\CalificacionParticipacion;
use App\Models\TrabajoGrupal;
use App\Models\CriterioTrabajoGrupal;
use App\Models\Grupo;
use App\Models\PuntajeCriterioGrupo;
use App\Models\AjusteIndividualGrupo;
use App\Models\Proyecto;
use App\Models\CriterioProyecto;
use App\Models\CalificacionProyecto;
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

    private function findCourse($uuid)
    {
        return Curso::where('uuid', $uuid)->where('usuario_id', auth()->id())->firstOrFail();
    }

    private function findUnit($course, $unitUuid)
    {
        return Unidad::where('uuid', $unitUuid)->where('curso_id', $course->id)->firstOrFail();
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
            $this->softDelete($est);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
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
            $this->softDelete($sesion);
        }

        $tareas = Tarea::where('unidad_id', $unidad->id)->get();
        foreach ($tareas as $tarea) {
            $this->softDelete($tarea);
        }

        $practicas = Practica::where('unidad_id', $unidad->id)->get();
        foreach ($practicas as $practica) {
            $this->softDelete($practica);
        }

        $itemsParticipacion = ItemParticipacion::where('unidad_id', $unidad->id)->get();
        foreach ($itemsParticipacion as $item) {
            $this->softDelete($item);
        }

        $trabajosGrupales = TrabajoGrupal::where('unidad_id', $unidad->id)->get();
        foreach ($trabajosGrupales as $tg) {
            $this->softDelete($tg);
        }

        $proyectos = Proyecto::where('unidad_id', $unidad->id)->get();
        foreach ($proyectos as $proyecto) {
            $this->softDelete($proyecto);
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

            foreach ($request->records ?? [] as $rec) {
                $est = Estudiante::where('uuid', $rec['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    RegistroAsistencia::create([
                        'uuid' => Str::uuid()->toString(), 'sesion_id' => $sesion->id,
                        'estudiante_id' => $est->id,
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
                foreach ($request->records as $rec) {
                    $est = Estudiante::where('uuid', $rec['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        RegistroAsistencia::create([
                            'uuid' => Str::uuid()->toString(), 'sesion_id' => $sesion->id,
                            'estudiante_id' => $est->id,
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
            $this->softDelete($sesion);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    // ===================== 4.4 TASKS =====================

    public function storeTask($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string', 'date' => 'required|date']);

        DB::beginTransaction();
        try {
            $tarea = Tarea::create([
                'uuid' => $request->id, 'unidad_id' => $unidad->id,
                'nombre' => $request->name, 'fecha' => $request->date,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionTarea::create([
                        'uuid' => Str::uuid()->toString(), 'tarea_id' => $tarea->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
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

    public function updateTask($courseUuid, $unitUuid, $taskUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $tarea = Tarea::where('uuid', $taskUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        if ($request->has('date')) { $data['fecha'] = $request->date; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        if (!empty($data)) { $tarea->update($data); }

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyTask($courseUuid, $unitUuid, $taskUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $tarea = Tarea::where('uuid', $taskUuid)->where('unidad_id', $unidad->id)->first();
        if ($tarea) {
            $this->softDelete($tarea);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updateTaskScores($courseUuid, $unitUuid, $taskUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $tarea = Tarea::where('uuid', $taskUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        DB::beginTransaction();
        try {
            $tarea->calificaciones()->forceDelete();
            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionTarea::create([
                        'uuid' => Str::uuid()->toString(), 'tarea_id' => $tarea->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
                    ]);
                }
            }
            $tarea->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===================== 4.5 PRACTICES =====================

    public function storePractice($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string', 'date' => 'required|date']);

        DB::beginTransaction();
        try {
            $practica = Practica::create([
                'uuid' => $request->id, 'unidad_id' => $unidad->id,
                'nombre' => $request->name, 'fecha' => $request->date,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionPractica::create([
                        'uuid' => Str::uuid()->toString(), 'practica_id' => $practica->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
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

    public function updatePractice($courseUuid, $unitUuid, $practiceUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $practica = Practica::where('uuid', $practiceUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        if ($request->has('date')) { $data['fecha'] = $request->date; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        if (!empty($data)) { $practica->update($data); }

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyPractice($courseUuid, $unitUuid, $practiceUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $practica = Practica::where('uuid', $practiceUuid)->where('unidad_id', $unidad->id)->first();
        if ($practica) {
            $this->softDelete($practica);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updatePracticeScores($courseUuid, $unitUuid, $practiceUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $practica = Practica::where('uuid', $practiceUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        DB::beginTransaction();
        try {
            $practica->calificaciones()->forceDelete();
            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionPractica::create([
                        'uuid' => Str::uuid()->toString(), 'practica_id' => $practica->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
                    ]);
                }
            }
            $practica->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===================== 4.6 PARTICIPATION =====================

    public function storeParticipation($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string', 'date' => 'required|date']);

        DB::beginTransaction();
        try {
            $item = ItemParticipacion::create([
                'uuid' => $request->id, 'unidad_id' => $unidad->id,
                'nombre' => $request->name, 'fecha' => $request->date,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionParticipacion::create([
                        'uuid' => Str::uuid()->toString(), 'item_participacion_id' => $item->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
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

    public function updateParticipation($courseUuid, $unitUuid, $itemUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $item = ItemParticipacion::where('uuid', $itemUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        if ($request->has('date')) { $data['fecha'] = $request->date; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        if (!empty($data)) { $item->update($data); }

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyParticipation($courseUuid, $unitUuid, $itemUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $item = ItemParticipacion::where('uuid', $itemUuid)->where('unidad_id', $unidad->id)->first();
        if ($item) {
            $this->softDelete($item);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updateParticipationScores($courseUuid, $unitUuid, $itemUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $item = ItemParticipacion::where('uuid', $itemUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        DB::beginTransaction();
        try {
            $item->calificaciones()->forceDelete();
            foreach ($request->scores ?? [] as $sc) {
                $est = Estudiante::where('uuid', $sc['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    CalificacionParticipacion::create([
                        'uuid' => Str::uuid()->toString(), 'item_participacion_id' => $item->id,
                        'estudiante_id' => $est->id, 'puntaje' => $sc['score'],
                    ]);
                }
            }
            $item->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ===================== 4.7 GROUP WORKS =====================

    public function storeGroupWork($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $request->validate(['id' => 'required|string', 'name' => 'required|string', 'date' => 'required|date']);

        TrabajoGrupal::create([
            'uuid' => $request->id, 'unidad_id' => $unidad->id,
            'nombre' => $request->name, 'fecha' => $request->date,
            'created_at' => $request->input('created_at') ?? $now,
            'updated_at' => $request->input('updated_at') ?? $now,
            'deleted_at' => $request->input('deleted_at') ?? null,
            'sync_status' => 'synced',
            'device_id' => $request->input('device_id') ?? null,
        ]);

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function updateGroupWork($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();

        $data = [];
        if ($request->has('name')) { $data['nombre'] = $request->name; }
        if ($request->has('date')) { $data['fecha'] = $request->date; }
        $data['updated_at'] = $request->input('updated_at') ?? $now;
        $data['sync_status'] = 'synced';
        $data['device_id'] = $request->input('device_id') ?? null;
        $data['deleted_at'] = $request->input('deleted_at') ?? null;
        if (!empty($data)) { $gw->update($data); }

        $this->touchCourse($curso);

        return response()->json(['message' => 'ok'], 200);
    }

    public function destroyGroupWork($courseUuid, $unitUuid, $gwUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->first();
        if ($gw) {
            $grupos = Grupo::where('trabajo_grupal_id', $gw->id)->get();
            foreach ($grupos as $grupo) {
                $this->softDelete($grupo);
            }

            $criterios = CriterioTrabajoGrupal::where('trabajo_grupal_id', $gw->id)->get();
            foreach ($criterios as $criterio) {
                $this->softDelete($criterio);
            }

            $this->softDelete($gw);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updateGroupWorkCriteria($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $now = $this->now();

        DB::beginTransaction();
        try {
            $gw->criterios()->forceDelete();
            foreach ($request->criteria ?? [] as $c) {
                CriterioTrabajoGrupal::create([
                    'uuid' => $c['id'], 'trabajo_grupal_id' => $gw->id,
                    'nombre' => $c['name'], 'puntaje_maximo' => $c['maxScore'] ?? 5,
                ]);
            }
            $gw->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $request->validate(['id' => 'required|string', 'name' => 'required|string']);

        DB::beginTransaction();
        try {
            $grupo = Grupo::create([
                'uuid' => $request->id, 'trabajo_grupal_id' => $gw->id, 'nombre' => $request->name,
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            foreach ($request->studentIds ?? [] as $estUuid) {
                $est = Estudiante::where('uuid', $estUuid)->where('curso_id', $curso->id)->first();
                if ($est) {
                    DB::table('app_group_members')->insert(['grupo_id' => $grupo->id, 'estudiante_id' => $est->id]);
                }
            }

            foreach ($request->criterionScores ?? [] as $critUuid => $score) {
                $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)->where('trabajo_grupal_id', $gw->id)->first();
                if ($crit) {
                    PuntajeCriterioGrupo::create([
                        'uuid' => Str::uuid()->toString(), 'grupo_id' => $grupo->id,
                        'criterio_id' => $crit->id, 'puntaje' => $score,
                    ]);
                }
            }

            foreach ($request->overrides ?? [] as $ov) {
                $est = Estudiante::where('uuid', $ov['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    foreach ($ov['criterionScores'] ?? [] as $critUuid => $score) {
                        $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)->where('trabajo_grupal_id', $gw->id)->first();
                        if ($crit) {
                            AjusteIndividualGrupo::create([
                                'uuid' => Str::uuid()->toString(), 'grupo_id' => $grupo->id,
                                'estudiante_id' => $est->id, 'criterio_id' => $crit->id, 'puntaje' => $score,
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

    public function updateGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $grupo = Grupo::where('uuid', $groupUuid)->where('trabajo_grupal_id', $gw->id)->firstOrFail();

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
                DB::table('app_group_members')->where('grupo_id', $grupo->id)->delete();
                foreach ($request->studentIds as $estUuid) {
                    $est = Estudiante::where('uuid', $estUuid)->where('curso_id', $curso->id)->first();
                    if ($est) {
                        DB::table('app_group_members')->insert(['grupo_id' => $grupo->id, 'estudiante_id' => $est->id]);
                    }
                }
            }

            if ($request->has('criterionScores')) {
                $grupo->puntajesCriterio()->forceDelete();
                foreach ($request->criterionScores as $critUuid => $score) {
                    $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)->where('trabajo_grupal_id', $gw->id)->first();
                    if ($crit) {
                        PuntajeCriterioGrupo::create([
                            'uuid' => Str::uuid()->toString(), 'grupo_id' => $grupo->id,
                            'criterio_id' => $crit->id, 'puntaje' => $score,
                        ]);
                    }
                }
            }

            if ($request->has('overrides')) {
                $grupo->ajustesIndividuales()->forceDelete();
                foreach ($request->overrides as $ov) {
                    $est = Estudiante::where('uuid', $ov['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        foreach ($ov['criterionScores'] ?? [] as $critUuid => $score) {
                            $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)->where('trabajo_grupal_id', $gw->id)->first();
                            if ($crit) {
                                AjusteIndividualGrupo::create([
                                    'uuid' => Str::uuid()->toString(), 'grupo_id' => $grupo->id,
                                    'estudiante_id' => $est->id, 'criterio_id' => $crit->id, 'puntaje' => $score,
                                ]);
                            }
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

    public function destroyGroupWorkGroup($courseUuid, $unitUuid, $gwUuid, $groupUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $grupo = Grupo::where('uuid', $groupUuid)->where('trabajo_grupal_id', $gw->id)->first();
        if ($grupo) {
            $this->softDelete($grupo);
        }
        $this->touchCourse($curso);
        return response()->json(['ok' => true]);
    }

    public function updateGroupWorkOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $grupo = Grupo::where('uuid', $groupUuid)->where('trabajo_grupal_id', $gw->id)->firstOrFail();
        $est = Estudiante::where('uuid', $studentId)->where('curso_id', $curso->id)->firstOrFail();

        DB::beginTransaction();
        try {
            AjusteIndividualGrupo::where('grupo_id', $grupo->id)->where('estudiante_id', $est->id)->delete();

            foreach ($request->criterionScores ?? [] as $critUuid => $score) {
                $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)->where('trabajo_grupal_id', $gw->id)->first();
                if ($crit) {
                    AjusteIndividualGrupo::create([
                        'uuid' => Str::uuid()->toString(), 'grupo_id' => $grupo->id,
                        'estudiante_id' => $est->id, 'criterio_id' => $crit->id, 'puntaje' => $score,
                    ]);
                }
            }
            $gw->update(['updated_at' => $this->now()]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyGroupWorkOverride($courseUuid, $unitUuid, $gwUuid, $groupUuid, $studentId)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $gw = TrabajoGrupal::where('uuid', $gwUuid)->where('unidad_id', $unidad->id)->firstOrFail();
        $grupo = Grupo::where('uuid', $groupUuid)->where('trabajo_grupal_id', $gw->id)->firstOrFail();
        $est = Estudiante::where('uuid', $studentId)->where('curso_id', $curso->id)->firstOrFail();

        AjusteIndividualGrupo::where('grupo_id', $grupo->id)->where('estudiante_id', $est->id)->delete();
        $gw->update(['updated_at' => $this->now()]);
        $this->touchCourse($curso);
        return response()->json(null, 204);
    }

    // ===================== 4.8 PROJECT =====================

    public function storeProject($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();

        DB::beginTransaction();
        try {
            $proyecto = Proyecto::create([
                'uuid' => $request->input('id') ?? Str::uuid()->toString(), 'unidad_id' => $unidad->id,
                'nombre' => $request->name ?? 'Proyecto',
                'created_at' => $request->input('created_at') ?? $now,
                'updated_at' => $request->input('updated_at') ?? $now,
                'deleted_at' => $request->input('deleted_at') ?? null,
                'sync_status' => 'synced',
                'device_id' => $request->input('device_id') ?? null,
            ]);

            foreach ($request->criteria ?? [] as $c) {
                CriterioProyecto::create([
                    'uuid' => $c['id'], 'proyecto_id' => $proyecto->id,
                    'nombre' => $c['name'], 'puntaje_maximo' => $c['maxScore'] ?? 5,
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

    public function updateProject($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $proyecto = $unidad->proyectos()->firstOrFail();

        DB::beginTransaction();
        try {
            $data = [];
            if ($request->has('name')) { $data['nombre'] = $request->name; }
            $data['updated_at'] = $request->input('updated_at') ?? $now;
            $data['sync_status'] = 'synced';
            $data['device_id'] = $request->input('device_id') ?? null;
            $data['deleted_at'] = $request->input('deleted_at') ?? null;
            if (!empty($data)) { $proyecto->update($data); }

            if ($request->has('criteria')) {
                $proyecto->criterios()->forceDelete();
                foreach ($request->criteria as $c) {
                    CriterioProyecto::create([
                        'uuid' => $c['id'], 'proyecto_id' => $proyecto->id,
                        'nombre' => $c['name'], 'puntaje_maximo' => $c['maxScore'] ?? 5,
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

    public function destroyProject($courseUuid, $unitUuid)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $unidad->proyectos()->forceDelete();
        $this->touchCourse($curso);
        return response()->json(null, 204);
    }

    public function updateProjectStudentScores($courseUuid, $unitUuid, Request $request)
    {
        $curso = $this->findCourse($courseUuid);
        $unidad = $this->findUnit($curso, $unitUuid);
        $now = $this->now();
        $proyecto = $unidad->proyectos()->firstOrFail();

        DB::beginTransaction();
        try {
            $proyecto->calificaciones()->forceDelete();
            foreach ($request->scores ?? [] as $s) {
                $est = Estudiante::where('uuid', $s['studentId'])->where('curso_id', $curso->id)->first();
                if ($est) {
                    foreach ($s['criterionScores'] ?? [] as $critUuid => $score) {
                        $crit = CriterioProyecto::where('uuid', $critUuid)->where('proyecto_id', $proyecto->id)->first();
                        if ($crit) {
                            CalificacionProyecto::create([
                                'uuid' => Str::uuid()->toString(), 'proyecto_id' => $proyecto->id,
                                'estudiante_id' => $est->id, 'criterio_id' => $crit->id, 'puntaje' => $score,
                            ]);
                        }
                    }
                }
            }
            $proyecto->update(['updated_at' => $now]);
            DB::commit();
            $this->touchCourse($curso);
            return response()->json(['message' => 'ok'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
