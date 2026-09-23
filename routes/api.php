<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CourseSyncController;
use App\Http\Controllers\CourseEntityController;
use App\Http\Controllers\AlertSyncController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RubricSuggestionController;

// ========== ENDPOINTS PUBLICOS ==========
// Limitadores por IP (throttle:X,Y = X intentos por Y minutos) para evitar
// fuerza bruta en login/auth y uso abusivo del endpoint de prueba.

Route::post('/send-notification', [NotificationController::class, 'send'])->middleware('throttle:20,1');
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:10,1');

// --- Flutter contract: Auth ---
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/login/google', [AuthController::class, 'google'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// ========== ENDPOINTS AUTENTICADOS ==========

Route::middleware('auth:sanctum')->group(function () {
    // --- Flutter contract: User ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);

    // --- Flutter contract: Courses ---
    Route::get('/courses', [CourseSyncController::class, 'index']);
    Route::post('/courses/sync', [CourseSyncController::class, 'sync']);
    Route::post('/courses/{courseUuid}/restore', [CourseSyncController::class, 'restoreCourse']);
    Route::post('/courses', [CourseSyncController::class, 'store']);
    Route::get('/courses/{id}', [CourseSyncController::class, 'show']);
    Route::put('/courses/{id}', [CourseSyncController::class, 'update']);
    Route::delete('/courses/{id}', [CourseSyncController::class, 'destroy']);

    // --- Flutter contract: Individual entity endpoints ---
    Route::prefix('courses/{courseUuid}')->group(function () {
        // Students
        Route::post('/students/bulk', [CourseEntityController::class, 'storeStudentsBulk']);
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
        Route::put('/units/{unitUuid}/tasks/{taskUuid}/criteria', [CourseEntityController::class, 'updateTaskCriteria']);

        // Practices
        Route::post('/units/{unitUuid}/practices', [CourseEntityController::class, 'storePractice']);
        Route::put('/units/{unitUuid}/practices/{practiceUuid}', [CourseEntityController::class, 'updatePractice']);
        Route::delete('/units/{unitUuid}/practices/{practiceUuid}', [CourseEntityController::class, 'destroyPractice']);
        Route::put('/units/{unitUuid}/practices/{practiceUuid}/scores', [CourseEntityController::class, 'updatePracticeScores']);
        Route::put('/units/{unitUuid}/practices/{practiceUuid}/criteria', [CourseEntityController::class, 'updatePracticeCriteria']);

        // Participation
        Route::post('/units/{unitUuid}/participation', [CourseEntityController::class, 'storeParticipation']);
        Route::put('/units/{unitUuid}/participation/{itemUuid}', [CourseEntityController::class, 'updateParticipation']);
        Route::delete('/units/{unitUuid}/participation/{itemUuid}', [CourseEntityController::class, 'destroyParticipation']);
        Route::put('/units/{unitUuid}/participation/{itemUuid}/scores', [CourseEntityController::class, 'updateParticipationScores']);
        Route::put('/units/{unitUuid}/participation/{itemUuid}/criteria', [CourseEntityController::class, 'updateParticipationCriteria']);

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

        // Projects (múltiples por unidad)
        Route::post('/units/{unitUuid}/projects', [CourseEntityController::class, 'storeProject']);
        Route::put('/units/{unitUuid}/projects/{projectUuid}', [CourseEntityController::class, 'updateProject']);
        Route::delete('/units/{unitUuid}/projects/{projectUuid}', [CourseEntityController::class, 'destroyProject']);
        Route::put('/units/{unitUuid}/projects/{projectUuid}/scores', [CourseEntityController::class, 'updateProjectScores']);
        Route::put('/units/{unitUuid}/projects/{projectUuid}/criteria', [CourseEntityController::class, 'updateProjectCriteria']);

        // Actividades genéricas: gestión de grupos (válida para cualquier tipo de actividad)
        Route::post('/units/{unitUuid}/activities/{activityUuid}/groups', [CourseEntityController::class, 'storeActivityGroup']);
        Route::put('/units/{unitUuid}/activities/{activityUuid}/groups/{groupUuid}', [CourseEntityController::class, 'updateActivityGroup']);
        Route::delete('/units/{unitUuid}/activities/{activityUuid}/groups/{groupUuid}', [CourseEntityController::class, 'destroyActivityGroup']);
        Route::put('/units/{unitUuid}/activities/{activityUuid}/groups/{groupUuid}/overrides/{studentId}', [CourseEntityController::class, 'updateActivityOverride']);
        Route::delete('/units/{unitUuid}/activities/{activityUuid}/groups/{groupUuid}/overrides/{studentId}', [CourseEntityController::class, 'destroyActivityOverride']);

        // Settings
        Route::put('/settings', [CourseEntityController::class, 'updateSettings']);
        Route::patch('/selected-unit-index', [CourseEntityController::class, 'updateSelectedUnitIndex']);
    });

    // --- Flutter contract: Alerts ---
    Route::get('/alerts', [AlertSyncController::class, 'index']);
    Route::post('/alerts/sync', [AlertSyncController::class, 'sync']);
    Route::put('/alerts/{id}/read', [AlertSyncController::class, 'markRead']);

    // --- Rubric suggestions (plantillas por tipo de actividad) ---
    Route::get('/rubric-suggestions', [RubricSuggestionController::class, 'index']);
    Route::get('/rubric-suggestions/{type}', [RubricSuggestionController::class, 'show']);

    // --- Admin endpoints ---
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{uuid}', [AdminController::class, 'userDetail']);
        Route::get('/users/{uuid}/courses', [AdminController::class, 'userCourses']);
    });
});
