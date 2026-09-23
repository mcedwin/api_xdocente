<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Curso;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
            return $next($request);
        });
    }

    public function stats()
    {
        $totalUsers = User::count();
        $totalCourses = Curso::whereNull('deleted_at')->count();
        $totalStudents = DB::table('app_students')->whereNull('deleted_at')->count();

        $recentUsers = User::latest()->take(10)->get(['id', 'uuid', 'nombre', 'email', 'avatar', 'created_at']);

        return response()->json([
            'total_users' => $totalUsers,
            'total_courses' => $totalCourses,
            'total_students' => $totalStudents,
            'recent_users' => $recentUsers
        ]);
    }

    public function users()
    {
        $users = User::withCount(['cursos as courses_count' => function ($q) {
            $q->whereNull('deleted_at');
        }])->orderBy('created_at', 'desc')->get();

        return response()->json(['users' => $users]);
    }

    public function userDetail($uuid)
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $coursesCount = Curso::where('usuario_id', $user->id)->whereNull('deleted_at')->count();
        $studentsCount = DB::table('app_students')
            ->join('app_courses', 'app_courses.id', '=', 'app_students.curso_id')
            ->where('app_courses.usuario_id', $user->id)
            ->whereNull('app_students.deleted_at')
            ->whereNull('app_courses.deleted_at')
            ->count();

        return response()->json([
            'user' => $user,
            'courses_count' => $coursesCount,
            'students_count' => $studentsCount
        ]);
    }

    public function userCourses($uuid)
    {
        $user = User::where('uuid', $uuid)->firstOrFail();
        $courses = Curso::with(['estudiantes', 'unidades.sesiones', 'unidades.activities'])
            ->where('usuario_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $transformed = $courses->map(function ($course) {
            return $this->transformCourse($course);
        });

        return response()->json(['courses' => $transformed]);
    }

    private function transformCourse($course)
    {
        return [
            'uuid' => $course->uuid,
            'name' => $course->nombre,
            'description' => $course->descripcion,
            'selectedUnitIndex' => $course->indice_unidad_seleccionada,
            'settings' => [
                'maxTaskScore' => $course->puntaje_max_tarea,
                'maxPracticeScore' => $course->puntaje_max_practica,
                'maxParticipation' => $course->puntaje_max_participacion,
                'maxGroupWorkScore' => $course->puntaje_max_trabajo_grupal,
                'maxProjectScore' => $course->puntaje_max_proyecto,
                'pctAttendance' => $course->peso_asistencia,
                'pctTasks' => $course->peso_tareas,
                'pctPractices' => $course->peso_practicas,
                'pctParticipation' => $course->peso_participacion,
                'pctProject' => $course->peso_proyecto,
            ],
            'students' => $course->estudiantes->map(fn($s) => [
                'uuid' => $s->uuid,
                'name' => $s->nombre,
            ]),
            'units' => $course->unidades->map(fn($u) => $this->transformUnit($u)),
            'created_at' => $course->created_at,
        ];
    }

    private function transformUnit($unit)
    {
        $activities = $unit->activities ?? collect();
        return [
            'uuid' => $unit->uuid,
            'name' => $unit->nombre,
            'sessions' => $unit->sesiones->map(fn($s) => [
                'uuid' => $s->uuid,
                'date' => $s->fecha,
                'topic' => $s->tema,
            ]),
            'tasks' => $activities->where('type', 'task')->values()->map(fn($t) => [
                'uuid' => $t->uuid,
                'name' => $t->nombre,
                'date' => $t->fecha,
            ]),
            'practices' => $activities->where('type', 'practice')->values()->map(fn($p) => [
                'uuid' => $p->uuid,
                'name' => $p->nombre,
                'date' => $p->fecha,
            ]),
            'projects' => $activities->where('type', 'project')->values()->map(fn($p) => [
                'uuid' => $p->uuid,
                'name' => $p->nombre,
                'date' => $p->fecha,
            ]),
        ];
    }
}
