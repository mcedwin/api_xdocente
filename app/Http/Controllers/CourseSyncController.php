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

class CourseSyncController extends Controller
{
    private function now()
    {
        return now()->format('Y-m-d H:i:s');
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

    public function index(Request $request)
    {
        $query = Curso::where('usuario_id', auth()->id())
            ->with([
                'estudiantes',
                'unidades.sesiones.registrosAsistencia.estudiante',
                'unidades.tareas.calificaciones.estudiante',
                'unidades.practicas.calificaciones.estudiante',
                'unidades.itemsParticipacion.calificaciones.estudiante',
                'unidades.trabajosGrupales.criterios',
                'unidades.trabajosGrupales.grupos.estudiantes',
                'unidades.trabajosGrupales.grupos.puntajesCriterio.criterio',
                'unidades.trabajosGrupales.grupos.ajustesIndividuales.estudiante',
                'unidades.trabajosGrupales.grupos.ajustesIndividuales.criterio',
                'unidades.proyectos.criterios',
                'unidades.proyectos.calificaciones.estudiante',
                'unidades.proyectos.calificaciones.criterio',
            ]);

        if ($request->has('since')) {
            $since = $request->input('since');
            $query->where('updated_at', '>', $since);
        } else {
            $query->whereNull('deleted_at');
        }

        $cursos = $query->get();

        $result = $cursos->map(function ($curso) {
            return $this->formatCourse($curso);
        }); 

        return response()->json(['data' => $result]);
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
        $curso->puntaje_max_tarea = $request->input('settings.maxTaskScore', 5);
        $curso->puntaje_max_practica = $request->input('settings.maxPracticeScore', 5);
        $curso->puntaje_max_participacion = $request->input('settings.maxParticipation', 3);
        $curso->puntaje_max_trabajo_grupal = $request->input('settings.maxGroupWorkScore', 20);
        $curso->puntaje_max_proyecto = $request->input('settings.maxProjectScore', 5);
        $curso->updated_at = $request->input('updated_at') ?? $now;
        $curso->deleted_at = $request->input('deleted_at');
        $curso->sync_status = 'synced';
        $curso->device_id = $request->input('device_id');
        $curso->save();

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

            $unidades = $curso->unidades()->get();
            foreach ($unidades as $unidad) {
                $unidad->sesiones()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
                $unidad->tareas()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
                $unidad->practicas()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
                $unidad->itemsParticipacion()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
                $unidad->trabajosGrupales()->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
                $proyecto = $unidad->proyectos()->first();
                if ($proyecto) {
                    $proyecto->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                        'sync_status' => 'synced',
                    ]);
                }
                $unidad->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                    'sync_status' => 'synced',
                ]);
            }
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
        $curso->puntaje_max_tarea = $data['settings']['maxTaskScore'] ?? 5;
        $curso->puntaje_max_practica = $data['settings']['maxPracticeScore'] ?? 5;
        $curso->puntaje_max_participacion = $data['settings']['maxParticipation'] ?? 3;
        $curso->puntaje_max_trabajo_grupal = $data['settings']['maxGroupWorkScore'] ?? 20;
        $curso->puntaje_max_proyecto = $data['settings']['maxProjectScore'] ?? 5;
        $curso->updated_at = $data['updated_at'] ?? $now;
        $curso->deleted_at = $data['deleted_at'] ?? null;
        $curso->sync_status = 'synced';
        $curso->device_id = $data['device_id'] ?? null;
        $curso->save();

        if (!$isNew) {
            $curso->estudiantes()->forceDelete();
            $curso->unidades()->forceDelete();
        }

        $this->insertCourseChildren($curso, $data);

        return $curso;
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
                    $est = Estudiante::where('uuid', $recData['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        RegistroAsistencia::create([
                            'uuid' => Str::uuid()->toString(),
                            'sesion_id' => $sesion->id,
                            'estudiante_id' => $est->id,
                            'asistencia' => $this->attendanceToDb($recData['attendance'] ?? 'present'),
                            'observaciones' => $recData['observations'] ?? null,
                        ]);
                    }
                }
            }

            foreach ($uData['tasks'] ?? [] as $tData) {
                $tarea = Tarea::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $tData['id'],
                    'nombre' => $tData['name'],
                    'fecha' => $tData['date'],
                    'created_at' => $tData['created_at'] ?? $now,
                    'updated_at' => $tData['updated_at'] ?? $now,
                    'deleted_at' => $tData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $tData['device_id'] ?? null,
                ]);

                foreach ($tData['scores'] ?? [] as $scData) {
                    $est = Estudiante::where('uuid', $scData['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        CalificacionTarea::create([
                            'uuid' => Str::uuid()->toString(),
                            'tarea_id' => $tarea->id,
                            'estudiante_id' => $est->id,
                            'puntaje' => $scData['score'],
                        ]);
                    }
                }
            }

            foreach ($uData['practices'] ?? [] as $pData) {
                $practica = Practica::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $pData['id'],
                    'nombre' => $pData['name'],
                    'fecha' => $pData['date'],
                    'created_at' => $pData['created_at'] ?? $now,
                    'updated_at' => $pData['updated_at'] ?? $now,
                    'deleted_at' => $pData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $pData['device_id'] ?? null,
                ]);

                foreach ($pData['scores'] ?? [] as $scData) {
                    $est = Estudiante::where('uuid', $scData['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        CalificacionPractica::create([
                            'uuid' => Str::uuid()->toString(),
                            'practica_id' => $practica->id,
                            'estudiante_id' => $est->id,
                            'puntaje' => $scData['score'],
                        ]);
                    }
                }
            }

            foreach ($uData['participationItems'] ?? [] as $iData) {
                $item = ItemParticipacion::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $iData['id'],
                    'nombre' => $iData['name'],
                    'fecha' => $iData['date'],
                    'created_at' => $iData['created_at'] ?? $now,
                    'updated_at' => $iData['updated_at'] ?? $now,
                    'deleted_at' => $iData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $iData['device_id'] ?? null,
                ]);

                foreach ($iData['scores'] ?? [] as $scData) {
                    $est = Estudiante::where('uuid', $scData['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        CalificacionParticipacion::create([
                            'uuid' => Str::uuid()->toString(),
                            'item_participacion_id' => $item->id,
                            'estudiante_id' => $est->id,
                            'puntaje' => $scData['score'],
                        ]);
                    }
                }
            }

            foreach ($uData['groupWorks'] ?? [] as $gwData) {
                $trabajo = TrabajoGrupal::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $gwData['id'],
                    'nombre' => $gwData['name'],
                    'fecha' => $gwData['date'],
                    'created_at' => $gwData['created_at'] ?? $now,
                    'updated_at' => $gwData['updated_at'] ?? $now,
                    'deleted_at' => $gwData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $gwData['device_id'] ?? null,
                ]);

                foreach ($gwData['criteria'] ?? [] as $cData) {
                    CriterioTrabajoGrupal::create([
                        'trabajo_grupal_id' => $trabajo->id,
                        'uuid' => $cData['id'],
                        'nombre' => $cData['name'],
                        'puntaje_maximo' => $cData['maxScore'] ?? 5,
                    ]);
                }

                foreach ($gwData['groups'] ?? [] as $gData) {
                    $grupo = Grupo::create([
                        'trabajo_grupal_id' => $trabajo->id,
                        'uuid' => $gData['id'],
                        'nombre' => $gData['name'],
                        'created_at' => $gData['created_at'] ?? $now,
                        'updated_at' => $gData['updated_at'] ?? $now,
                        'deleted_at' => $gData['deleted_at'] ?? null,
                        'sync_status' => 'synced',
                        'device_id' => $gData['device_id'] ?? null,
                    ]);

                    foreach ($gData['studentIds'] ?? [] as $estUuid) {
                        $est = Estudiante::where('uuid', $estUuid)->where('curso_id', $curso->id)->first();
                        if ($est) {
                            DB::table('app_group_members')->insert([
                                'grupo_id' => $grupo->id,
                                'estudiante_id' => $est->id,
                            ]);
                        }
                    }

                    foreach ($gData['criterionScores'] ?? [] as $critUuid => $score) {
                        $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)
                            ->where('trabajo_grupal_id', $trabajo->id)->first();
                        if ($crit) {
                            PuntajeCriterioGrupo::create([
                                'uuid' => Str::uuid()->toString(),
                                'grupo_id' => $grupo->id,
                                'criterio_id' => $crit->id,
                                'puntaje' => $score,
                            ]);
                        }
                    }

                    foreach ($gData['overrides'] ?? [] as $ovData) {
                        $est = Estudiante::where('uuid', $ovData['studentId'])->where('curso_id', $curso->id)->first();
                        if ($est) {
                            foreach ($ovData['criterionScores'] ?? [] as $critUuid => $score) {
                                $crit = CriterioTrabajoGrupal::where('uuid', $critUuid)
                                    ->where('trabajo_grupal_id', $trabajo->id)->first();
                                if ($crit) {
                                    AjusteIndividualGrupo::create([
                                        'uuid' => Str::uuid()->toString(),
                                        'grupo_id' => $grupo->id,
                                        'estudiante_id' => $est->id,
                                        'criterio_id' => $crit->id,
                                        'puntaje' => $score,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            if (isset($uData['project']) && $uData['project'] !== null) {
                $projData = $uData['project'];
                $proyecto = Proyecto::create([
                    'unidad_id' => $unidad->id,
                    'uuid' => $projData['id'] ?? Str::uuid()->toString(),
                    'nombre' => $projData['name'] ?? 'Proyecto',
                    'created_at' => $projData['created_at'] ?? $now,
                    'updated_at' => $projData['updated_at'] ?? $now,
                    'deleted_at' => $projData['deleted_at'] ?? null,
                    'sync_status' => 'synced',
                    'device_id' => $projData['device_id'] ?? null,
                ]);

                foreach ($projData['criteria'] ?? [] as $pcData) {
                    CriterioProyecto::create([
                        'proyecto_id' => $proyecto->id,
                        'uuid' => $pcData['id'],
                        'nombre' => $pcData['name'],
                        'puntaje_maximo' => $pcData['maxScore'] ?? 5,
                    ]);
                }

                foreach ($projData['scores'] ?? [] as $psData) {
                    $est = Estudiante::where('uuid', $psData['studentId'])->where('curso_id', $curso->id)->first();
                    if ($est) {
                        foreach ($psData['criterionScores'] ?? [] as $critUuid => $score) {
                            $crit = CriterioProyecto::where('uuid', $critUuid)->where('proyecto_id', $proyecto->id)->first();
                            if ($crit) {
                                CalificacionProyecto::create([
                                    'uuid' => Str::uuid()->toString(),
                                    'proyecto_id' => $proyecto->id,
                                    'estudiante_id' => $est->id,
                                    'criterio_id' => $crit->id,
                                    'puntaje' => $score,
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function formatCourse($curso)
    {
        $course = [
            'id' => $curso->uuid ?? (string) $curso->id,
            'name' => $curso->nombre,
        ];

        if ($curso->descripcion !== null) {
            $course['description'] = $curso->descripcion;
        }

        $course['created_at'] = $curso->created_at;
        $course['updated_at'] = $curso->updated_at;

        if ($curso->deleted_at !== null) {
            $course['deleted_at'] = $curso->deleted_at;
        }

        $course['sync_status'] = $curso->sync_status ?? 'synced';

        if ($curso->device_id !== null) {
            $course['device_id'] = $curso->device_id;
        }

        $course['settings'] = [
            'maxTaskScore' => (float) ($curso->puntaje_max_tarea ?? 5),
            'maxPracticeScore' => (float) ($curso->puntaje_max_practica ?? 5),
            'maxParticipation' => (float) ($curso->puntaje_max_participacion ?? 3),
            'maxGroupWorkScore' => (float) ($curso->puntaje_max_trabajo_grupal ?? 20),
            'maxProjectScore' => (float) ($curso->puntaje_max_proyecto ?? 5),
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
        $s['created_at'] = $est->created_at;
        $s['updated_at'] = $est->updated_at;
        if ($est->deleted_at !== null) {
            $s['deleted_at'] = $est->deleted_at;
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
            'created_at' => $unidad->created_at,
            'updated_at' => $unidad->updated_at,
        ];
        if ($unidad->deleted_at !== null) {
            $unit['deleted_at'] = $unidad->deleted_at;
        }
        $unit['sync_status'] = $unidad->sync_status ?? 'synced';
        if ($unidad->device_id !== null) {
            $unit['device_id'] = $unidad->device_id;
        }

        $unit['sessions'] = $unidad->sesiones->map(function ($sesion) {
            return $this->formatSession($sesion);
        })->values()->toArray();

        $unit['tasks'] = $unidad->tareas->map(function ($tarea) {
            return $this->formatTask($tarea);
        })->values()->toArray();

        $unit['practices'] = $unidad->practicas->map(function ($practica) {
            return $this->formatPractice($practica);
        })->values()->toArray();

        $unit['participationItems'] = $unidad->itemsParticipacion->map(function ($item) {
            return $this->formatParticipationItem($item);
        })->values()->toArray();

        $unit['groupWorks'] = $unidad->trabajosGrupales->map(function ($tg) {
            return $this->formatGroupWork($tg);
        })->values()->toArray();

        $proyecto = $unidad->proyectos->first();
        $unit['project'] = $proyecto ? $this->formatProject($proyecto) : null;

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
        $s['created_at'] = $sesion->created_at;
        $s['updated_at'] = $sesion->updated_at;
        if ($sesion->deleted_at !== null) {
            $s['deleted_at'] = $sesion->deleted_at;
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

    private function formatTask($tarea)
    {
        return [
            'id' => $tarea->uuid ?? (string) $tarea->id,
            'name' => $tarea->nombre,
            'date' => $tarea->fecha instanceof \Carbon\Carbon
                ? $tarea->fecha->format('Y-m-d')
                : $tarea->fecha,
            'created_at' => $tarea->created_at,
            'updated_at' => $tarea->updated_at,
            'deleted_at' => $tarea->deleted_at ?? null,
            'sync_status' => $tarea->sync_status ?? 'synced',
            'device_id' => $tarea->device_id ?? null,
            'scores' => $tarea->calificaciones->map(function ($cal) {
                return [
                    'studentId' => $cal->estudiante->uuid ?? (string) $cal->estudiante_id,
                    'score' => (float) $cal->puntaje,
                ];
            })->values()->toArray(),
        ];
    }

    private function formatPractice($practica)
    {
        return [
            'id' => $practica->uuid ?? (string) $practica->id,
            'name' => $practica->nombre,
            'date' => $practica->fecha instanceof \Carbon\Carbon
                ? $practica->fecha->format('Y-m-d')
                : $practica->fecha,
            'created_at' => $practica->created_at,
            'updated_at' => $practica->updated_at,
            'deleted_at' => $practica->deleted_at ?? null,
            'sync_status' => $practica->sync_status ?? 'synced',
            'device_id' => $practica->device_id ?? null,
            'scores' => $practica->calificaciones->map(function ($cal) {
                return [
                    'studentId' => $cal->estudiante->uuid ?? (string) $cal->estudiante_id,
                    'score' => (float) $cal->puntaje,
                ];
            })->values()->toArray(),
        ];
    }

    private function formatParticipationItem($item)
    {
        return [
            'id' => $item->uuid ?? (string) $item->id,
            'name' => $item->nombre,
            'date' => $item->fecha instanceof \Carbon\Carbon
                ? $item->fecha->format('Y-m-d')
                : $item->fecha,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'deleted_at' => $item->deleted_at ?? null,
            'sync_status' => $item->sync_status ?? 'synced',
            'device_id' => $item->device_id ?? null,
            'scores' => $item->calificaciones->map(function ($cal) {
                return [
                    'studentId' => $cal->estudiante->uuid ?? (string) $cal->estudiante_id,
                    'score' => (float) $cal->puntaje,
                ];
            })->values()->toArray(),
        ];
    }

    private function formatGroupWork($tg)
    {
        $gw = [
            'id' => $tg->uuid ?? (string) $tg->id,
            'name' => $tg->nombre,
            'date' => $tg->fecha instanceof \Carbon\Carbon
                ? $tg->fecha->format('Y-m-d')
                : $tg->fecha,
            'created_at' => $tg->created_at,
            'updated_at' => $tg->updated_at,
            'deleted_at' => $tg->deleted_at ?? null,
            'sync_status' => $tg->sync_status ?? 'synced',
            'device_id' => $tg->device_id ?? null,
            'criteria' => $tg->criterios->map(function ($c) {
                return [
                    'id' => $c->uuid ?? (string) $c->id,
                    'name' => $c->nombre,
                    'maxScore' => (float) ($c->puntaje_maximo ?? 5),
                ];
            })->values()->toArray(),
            'groups' => $tg->grupos->map(function ($g) {
                $criterionScores = [];
                foreach ($g->puntajesCriterio as $pc) {
                    $critUuid = $pc->criterio->uuid ?? (string) $pc->criterio_id;
                    $criterionScores[$critUuid] = (float) $pc->puntaje;
                }

                $overrides = [];
                foreach ($g->ajustesIndividuales->groupBy('estudiante_id') as $estId => $ajustes) {
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

                return [
                    'id' => $g->uuid ?? (string) $g->id,
                    'name' => $g->nombre,
                    'studentIds' => $g->estudiantes->map(function ($e) {
                        return $e->uuid ?? (string) $e->id;
                    })->values()->toArray(),
                    'criterionScores' => $criterionScores,
                    'created_at' => $g->created_at,
                    'updated_at' => $g->updated_at,
                    'deleted_at' => $g->deleted_at ?? null,
                    'sync_status' => $g->sync_status ?? 'synced',
                    'device_id' => $g->device_id ?? null,
                    'overrides' => $overrides,
                ];
            })->values()->toArray(),
        ];
        return $gw;
    }

    private function formatProject($proyecto)
    {
        $scores = [];
        foreach ($proyecto->calificaciones->groupBy('estudiante_id') as $estId => $cals) {
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

        return [
            'id' => $proyecto->uuid ?? (string) $proyecto->id,
            'name' => $proyecto->nombre ?? 'Proyecto',
            'created_at' => $proyecto->created_at,
            'updated_at' => $proyecto->updated_at,
            'deleted_at' => $proyecto->deleted_at ?? null,
            'sync_status' => $proyecto->sync_status ?? 'synced',
            'device_id' => $proyecto->device_id ?? null,
            'criteria' => $proyecto->criterios->map(function ($c) {
                return [
                    'id' => $c->uuid ?? (string) $c->id,
                    'name' => $c->nombre,
                    'maxScore' => (float) ($c->puntaje_maximo ?? 5),
                ];
            })->values()->toArray(),
            'scores' => $scores,
        ];
    }
}
