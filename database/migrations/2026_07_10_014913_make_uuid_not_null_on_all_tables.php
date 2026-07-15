<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'cursos',
        'estudiantes',
        'unidades',
        'sesiones',
        'registros_asistencia',
        'tareas',
        'calificaciones_tareas',
        'practicas',
        'calificaciones_practicas',
        'items_participacion',
        'calificaciones_participacion',
        'trabajos_grupales',
        'criterios_trabajo_grupal',
        'grupos',
        'puntajes_criterio_grupo',
        'ajustes_individuales_grupo',
        'proyectos',
        'criterios_proyecto',
        'calificaciones_proyecto',
        'alertas',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Backfill null uuids (UUID() is evaluated per row)
            DB::statement("UPDATE `{$table}` SET `uuid` = UUID() WHERE `uuid` IS NULL");
            // Make NOT NULL
            DB::statement("ALTER TABLE `{$table}` MODIFY `uuid` VARCHAR(36) NOT NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `uuid` VARCHAR(36) NULL");
        }
    }
};
