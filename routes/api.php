<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\RegistroAsistenciaController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\CalificacionTareaController;
use App\Http\Controllers\PracticaController;
use App\Http\Controllers\CalificacionPracticaController;
use App\Http\Controllers\ParticipacionController;
use App\Http\Controllers\CalificacionParticipacionController;
use App\Http\Controllers\TrabajoGrupalController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\CriterioTrabajoGrupalController;
use App\Http\Controllers\PuntajeCriterioGrupoController;
use App\Http\Controllers\AjusteIndividualGrupoController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\CriterioProyectoController;
use App\Http\Controllers\CalificacionProyectoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CourseSyncController;
use App\Http\Controllers\CourseEntityController;
use App\Http\Controllers\AlertSyncController;

// ========== ENDPOINTS PÚBLICOS ==========

Route::post('/send-notification', [NotificationController::class, 'send']);
Route::post('/auth/google', [AuthController::class, 'google']);

// --- Flutter contract: Auth ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/google', [AuthController::class, 'google']);
Route::post('/register', [AuthController::class, 'register']);

// ========== ENDPOINTS AUTENTICADOS ==========

Route::middleware('auth:sanctum')->group(function () {
    // --- Flutter contract: User ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // --- Flutter contract: Courses ---
    Route::get('/courses', [CourseSyncController::class, 'index']);
    Route::post('/courses/sync', [CourseSyncController::class, 'sync']);
    Route::post('/courses', [CourseSyncController::class, 'store']);
    Route::delete('/courses/{id}', [CourseSyncController::class, 'destroy']);

    // --- Flutter contract: Individual entity endpoints ---
    Route::prefix('courses/{courseUuid}')->group(function () {
        // Students
        Route::post('/students', [CourseEntityController::class, 'storeStudent']);
        Route::put('/students/{studentUuid}', [CourseEntityController::class, 'updateStudent']);
        Route::delete('/students/{studentUuid}', [CourseEntityController::class, 'destroyStudent']);

        // Units
        Route::post('/units', [CourseEntityController::class, 'storeUnit']);
        Route::put('/units/{unitUuid}', [CourseEntityController::class, 'updateUnit']);
        Route::delete('/units/{unitUuid}', [CourseEntityController::class, 'destroyUnit']);

        // Sessions (nested under unit)
        Route::post('/units/{unitUuid}/sessions', [CourseEntityController::class, 'storeSession']);
        Route::put('/units/{unitUuid}/sessions/{sessionUuid}', [CourseEntityController::class, 'updateSession']);
        Route::delete('/units/{unitUuid}/sessions/{sessionUuid}', [CourseEntityController::class, 'destroySession']);

        // Tasks
        Route::post('/units/{unitUuid}/tasks', [CourseEntityController::class, 'storeTask']);
        Route::put('/units/{unitUuid}/tasks/{taskUuid}', [CourseEntityController::class, 'updateTask']);
        Route::delete('/units/{unitUuid}/tasks/{taskUuid}', [CourseEntityController::class, 'destroyTask']);
        Route::put('/units/{unitUuid}/tasks/{taskUuid}/scores', [CourseEntityController::class, 'updateTaskScores']);

        // Practices
        Route::post('/units/{unitUuid}/practices', [CourseEntityController::class, 'storePractice']);
        Route::put('/units/{unitUuid}/practices/{practiceUuid}', [CourseEntityController::class, 'updatePractice']);
        Route::delete('/units/{unitUuid}/practices/{practiceUuid}', [CourseEntityController::class, 'destroyPractice']);
        Route::put('/units/{unitUuid}/practices/{practiceUuid}/scores', [CourseEntityController::class, 'updatePracticeScores']);

        // Participation
        Route::post('/units/{unitUuid}/participation', [CourseEntityController::class, 'storeParticipation']);
        Route::put('/units/{unitUuid}/participation/{itemUuid}', [CourseEntityController::class, 'updateParticipation']);
        Route::delete('/units/{unitUuid}/participation/{itemUuid}', [CourseEntityController::class, 'destroyParticipation']);
        Route::put('/units/{unitUuid}/participation/{itemUuid}/scores', [CourseEntityController::class, 'updateParticipationScores']);

        // Group Works
        Route::post('/units/{unitUuid}/group-works', [CourseEntityController::class, 'storeGroupWork']);
        Route::put('/units/{unitUuid}/group-works/{gwUuid}', [CourseEntityController::class, 'updateGroupWork']);
        Route::delete('/units/{unitUuid}/group-works/{gwUuid}', [CourseEntityController::class, 'destroyGroupWork']);
        Route::put('/units/{unitUuid}/group-works/{gwUuid}/criteria', [CourseEntityController::class, 'updateGroupWorkCriteria']);
        Route::post('/units/{unitUuid}/group-works/{gwUuid}/groups', [CourseEntityController::class, 'storeGroupWorkGroup']);
        Route::put('/units/{unitUuid}/group-works/{gwUuid}/groups/{groupUuid}', [CourseEntityController::class, 'updateGroupWorkGroup']);
        Route::delete('/units/{unitUuid}/group-works/{gwUuid}/groups/{groupUuid}', [CourseEntityController::class, 'destroyGroupWorkGroup']);
        Route::put('/units/{unitUuid}/group-works/{gwUuid}/groups/{groupUuid}/overrides/{studentId}', [CourseEntityController::class, 'updateGroupWorkOverride']);
        Route::delete('/units/{unitUuid}/group-works/{gwUuid}/groups/{groupUuid}/overrides/{studentId}', [CourseEntityController::class, 'destroyGroupWorkOverride']);

        // Project
        Route::post('/units/{unitUuid}/project', [CourseEntityController::class, 'storeProject']);
        Route::put('/units/{unitUuid}/project', [CourseEntityController::class, 'updateProject']);
        Route::delete('/units/{unitUuid}/project', [CourseEntityController::class, 'destroyProject']);
        Route::put('/units/{unitUuid}/project/student-scores', [CourseEntityController::class, 'updateProjectStudentScores']);

        // Settings
        Route::put('/settings', [CourseEntityController::class, 'updateSettings']);
        Route::patch('/selected-unit-index', [CourseEntityController::class, 'updateSelectedUnitIndex']);
    });

    // --- Flutter contract: Alerts ---
    Route::get('/alerts', [AlertSyncController::class, 'index']);
    Route::post('/alerts/sync', [AlertSyncController::class, 'sync']);
    Route::put('/alerts/{id}/read', [AlertSyncController::class, 'markRead']);

    // ========== ENDPOINTS LEGADO (ESPAÑOL) ==========

    Route::get('/me', fn ($request) => $request->user());

    Route::apiResource('cursos', CursoController::class);

    Route::get('/cursos/{curso}/estudiantes', [EstudianteController::class, 'index']);
    Route::post('/cursos/{curso}/estudiantes', [EstudianteController::class, 'store']);
    Route::get('/estudiantes/{estudiante}', [EstudianteController::class, 'show']);
    Route::put('/estudiantes/{estudiante}', [EstudianteController::class, 'update']);
    Route::delete('/estudiantes/{estudiante}', [EstudianteController::class, 'destroy']);

    Route::get('/cursos/{curso}/unidades', [UnidadController::class, 'index']);
    Route::post('/cursos/{curso}/unidades', [UnidadController::class, 'store']);
    Route::get('/unidades/{unidad}', [UnidadController::class, 'show']);
    Route::put('/unidades/{unidad}', [UnidadController::class, 'update']);
    Route::delete('/unidades/{unidad}', [UnidadController::class, 'destroy']);

    Route::get('/unidades/{unidad}/sesiones', [SesionController::class, 'index']);
    Route::post('/unidades/{unidad}/sesiones', [SesionController::class, 'store']);
    Route::get('/sesiones/{sesion}', [SesionController::class, 'show']);
    Route::put('/sesiones/{sesion}', [SesionController::class, 'update']);
    Route::delete('/sesiones/{sesion}', [SesionController::class, 'destroy']);

    Route::get('/sesiones/{sesion}/asistencia', [RegistroAsistenciaController::class, 'index']);
    Route::post('/sesiones/{sesion}/asistencia', [RegistroAsistenciaController::class, 'store']);
    Route::put('/asistencia/{registro}', [RegistroAsistenciaController::class, 'update']);
    Route::delete('/asistencia/{registro}', [RegistroAsistenciaController::class, 'destroy']);

    Route::get('/unidades/{unidad}/tareas', [TareaController::class, 'index']);
    Route::post('/unidades/{unidad}/tareas', [TareaController::class, 'store']);
    Route::get('/tareas/{tarea}', [TareaController::class, 'show']);
    Route::put('/tareas/{tarea}', [TareaController::class, 'update']);
    Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy']);

    Route::post('/tareas/{tarea}/calificaciones', [CalificacionTareaController::class, 'store']);
    Route::put('/calificaciones-tareas/{calificacion}', [CalificacionTareaController::class, 'update']);

    Route::get('/unidades/{unidad}/practicas', [PracticaController::class, 'index']);
    Route::post('/unidades/{unidad}/practicas', [PracticaController::class, 'store']);
    Route::get('/practicas/{practica}', [PracticaController::class, 'show']);
    Route::put('/practicas/{practica}', [PracticaController::class, 'update']);
    Route::delete('/practicas/{practica}', [PracticaController::class, 'destroy']);

    Route::post('/practicas/{practica}/calificaciones', [CalificacionPracticaController::class, 'store']);
    Route::put('/calificaciones-practicas/{calificacion}', [CalificacionPracticaController::class, 'update']);

    Route::get('/unidades/{unidad}/participacion', [ParticipacionController::class, 'index']);
    Route::post('/unidades/{unidad}/participacion', [ParticipacionController::class, 'store']);
    Route::get('/participacion/{item}', [ParticipacionController::class, 'show']);
    Route::put('/participacion/{item}', [ParticipacionController::class, 'update']);
    Route::delete('/participacion/{item}', [ParticipacionController::class, 'destroy']);

    Route::post('/participacion/{item}/calificaciones', [CalificacionParticipacionController::class, 'store']);
    Route::put('/calificaciones-participacion/{calificacion}', [CalificacionParticipacionController::class, 'update']);

    Route::get('/unidades/{unidad}/trabajos-grupales', [TrabajoGrupalController::class, 'index']);
    Route::post('/unidades/{unidad}/trabajos-grupales', [TrabajoGrupalController::class, 'store']);
    Route::get('/trabajos-grupales/{trabajo}', [TrabajoGrupalController::class, 'show']);
    Route::put('/trabajos-grupales/{trabajo}', [TrabajoGrupalController::class, 'update']);
    Route::delete('/trabajos-grupales/{trabajo}', [TrabajoGrupalController::class, 'destroy']);

    Route::get('/trabajos-grupales/{trabajo}/criterios', [CriterioTrabajoGrupalController::class, 'index']);
    Route::post('/trabajos-grupales/{trabajo}/criterios', [CriterioTrabajoGrupalController::class, 'store']);
    Route::put('/criterios-trabajo-grupal/{criterio}', [CriterioTrabajoGrupalController::class, 'update']);
    Route::delete('/criterios-trabajo-grupal/{criterio}', [CriterioTrabajoGrupalController::class, 'destroy']);

    Route::get('/trabajos-grupales/{trabajo}/grupos', [GrupoController::class, 'index']);
    Route::post('/trabajos-grupales/{trabajo}/grupos', [GrupoController::class, 'store']);
    Route::get('/grupos/{grupo}', [GrupoController::class, 'show']);
    Route::put('/grupos/{grupo}', [GrupoController::class, 'update']);
    Route::delete('/grupos/{grupo}', [GrupoController::class, 'destroy']);

    Route::post('/grupos/{grupo}/puntajes', [PuntajeCriterioGrupoController::class, 'store']);
    Route::put('/puntajes-criterio-grupo/{puntaje}', [PuntajeCriterioGrupoController::class, 'update']);

    Route::get('/grupos/{grupo}/ajustes', [AjusteIndividualGrupoController::class, 'index']);
    Route::post('/grupos/{grupo}/ajustes', [AjusteIndividualGrupoController::class, 'store']);
    Route::put('/ajustes-individuales/{ajuste}', [AjusteIndividualGrupoController::class, 'update']);
    Route::delete('/ajustes-individuales/{ajuste}', [AjusteIndividualGrupoController::class, 'destroy']);

    Route::get('/unidades/{unidad}/proyectos', [ProyectoController::class, 'index']);
    Route::post('/unidades/{unidad}/proyectos', [ProyectoController::class, 'store']);
    Route::get('/proyectos/{proyecto}', [ProyectoController::class, 'show']);
    Route::put('/proyectos/{proyecto}', [ProyectoController::class, 'update']);
    Route::delete('/proyectos/{proyecto}', [ProyectoController::class, 'destroy']);

    Route::get('/proyectos/{proyecto}/criterios', [CriterioProyectoController::class, 'index']);
    Route::post('/proyectos/{proyecto}/criterios', [CriterioProyectoController::class, 'store']);
    Route::put('/criterios-proyecto/{criterio}', [CriterioProyectoController::class, 'update']);
    Route::delete('/criterios-proyecto/{criterio}', [CriterioProyectoController::class, 'destroy']);

    Route::post('/proyectos/{proyecto}/calificaciones', [CalificacionProyectoController::class, 'store']);
    Route::put('/calificaciones-proyecto/{calificacion}', [CalificacionProyectoController::class, 'update']);

    Route::get('/alertas', [AlertaController::class, 'index']);
    Route::post('/alertas', [AlertaController::class, 'store']);
    Route::get('/alertas/{alerta}', [AlertaController::class, 'show']);
    Route::put('/alertas/{alerta}/leida', [AlertaController::class, 'marcarLeida']);
    Route::delete('/alertas/{alerta}', [AlertaController::class, 'destroy']);
});
