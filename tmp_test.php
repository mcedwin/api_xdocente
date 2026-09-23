<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Activity;
use App\Models\ActivityScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$pdo = DB::connection('sqlite')->getPdo();

// Crear tablas mínimas de prueba
$pdo->exec("
    CREATE TABLE IF NOT EXISTS app_courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid VARCHAR(36),
        usuario_id INTEGER,
        nombre VARCHAR(255),
        descripcion TEXT,
        indice_unidad_seleccionada INTEGER DEFAULT 0,
        puntaje_max_tarea DECIMAL(5,2) DEFAULT 5,
        puntaje_max_practica DECIMAL(5,2) DEFAULT 5,
        puntaje_max_participacion DECIMAL(5,2) DEFAULT 3,
        puntaje_max_trabajo_grupal DECIMAL(5,2) DEFAULT 20,
        puntaje_max_proyecto DECIMAL(5,2) DEFAULT 5,
        sync_status VARCHAR(20) DEFAULT 'synced',
        device_id VARCHAR(36),
        created_at VARCHAR(30),
        updated_at VARCHAR(30),
        deleted_at VARCHAR(30)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS app_students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid VARCHAR(36),
        curso_id INTEGER,
        nombre VARCHAR(255),
        notas TEXT,
        sync_status VARCHAR(20) DEFAULT 'synced',
        device_id VARCHAR(36),
        created_at VARCHAR(30),
        updated_at VARCHAR(30),
        deleted_at VARCHAR(30)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS app_units (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid VARCHAR(36),
        curso_id INTEGER,
        nombre VARCHAR(255),
        orden INTEGER DEFAULT 0,
        sync_status VARCHAR(20) DEFAULT 'synced',
        device_id VARCHAR(36),
        created_at VARCHAR(30),
        updated_at VARCHAR(30),
        deleted_at VARCHAR(30)
    )
");

$now = now()->format('Y-m-d H:i:s');

// Insertar datos de prueba
$pdo->exec("DELETE FROM app_courses");
$pdo->exec("DELETE FROM app_students");
$pdo->exec("DELETE FROM app_units");

$courseUuid = Str::uuid()->toString();
$studentUuid = Str::uuid()->toString();
$unitUuid = Str::uuid()->toString();

$pdo->exec("INSERT INTO app_courses (uuid, usuario_id, nombre, created_at, updated_at) VALUES ('{$courseUuid}', 1, 'Curso Test', '{$now}', '{$now}')");
$pdo->exec("INSERT INTO app_students (uuid, curso_id, nombre, created_at, updated_at) VALUES ('{$studentUuid}', 1, 'Juan Pérez', '{$now}', '{$now}')");
$pdo->exec("INSERT INTO app_units (uuid, curso_id, nombre, created_at, updated_at) VALUES ('{$unitUuid}', 1, 'Unidad 1', '{$now}', '{$now}')");

// Crear actividad
$activity = Activity::create([
    'uuid' => Str::uuid()->toString(),
    'unidad_id' => 1,
    'type' => 'task',
    'nombre' => 'Tarea 1',
    'fecha' => '2026-09-21',
    'is_group_based' => false,
    'uses_rubric' => false,
    'created_at' => $now,
    'updated_at' => $now,
]);

ActivityScore::create([
    'uuid' => Str::uuid()->toString(),
    'activity_id' => $activity->id,
    'estudiante_id' => 1,
    'puntaje' => 4.5,
    'created_at' => $now,
    'updated_at' => $now,
]);

// Verificar
$loaded = Activity::with('scores.estudiante')->find($activity->id);
echo "Actividad creada: {$loaded->nombre} ({$loaded->type})\n";
echo "Scores: " . $loaded->scores->count() . "\n";
echo "Estudiante: " . ($loaded->scores->first()->estudiante->nombre ?? 'N/A') . "\n";
echo "Puntaje: " . $loaded->scores->first()->puntaje . "\n";

// Probar formato de CourseSyncController
$controller = new \App\Http\Controllers\CourseSyncController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('formatActivityItem');
$method->setAccessible(true);
$formatted = $method->invoke($controller, $loaded);
echo "\nFormato Actividad:\n";
echo json_encode($formatted, JSON_PRETTY_PRINT) . "\n";
