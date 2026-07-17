<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CourseSyncController;
use App\Http\Controllers\CourseEntityController;
use App\Http\Controllers\AlertSyncController;

// ========== ENDPOINTS PUBLICOS ==========

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
});
