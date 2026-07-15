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
            $hasCreatedAt = DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'created_at'");
            if (empty($hasCreatedAt)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `created_at` VARCHAR(30) NULL AFTER `uuid`");
            }

            $hasUpdatedAt = DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'updated_at'");
            if (empty($hasUpdatedAt)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `updated_at` VARCHAR(30) NULL AFTER `created_at`");
            }

            $hasDeletedAt = DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'deleted_at'");
            if (empty($hasDeletedAt)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `deleted_at` VARCHAR(30) NULL DEFAULT NULL AFTER `updated_at`");
            }

            $hasSyncStatus = DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'sync_status'");
            if (empty($hasSyncStatus)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `sync_status` VARCHAR(20) DEFAULT 'synced' AFTER `deleted_at`");
            }

            $hasDeviceId = DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'device_id'");
            if (empty($hasDeviceId)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `device_id` VARCHAR(36) NULL DEFAULT NULL AFTER `sync_status`");
            }

            DB::statement("UPDATE `{$table}` SET `created_at` = UTC_TIMESTAMP WHERE `created_at` IS NULL");
            DB::statement("UPDATE `{$table}` SET `updated_at` = UTC_TIMESTAMP WHERE `updated_at` IS NULL");

            $indexName = "idx_{$table}_updated_at";
            $hasIndex = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
            if (empty($hasIndex)) {
                DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`updated_at`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `created_at`");
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `updated_at`");
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `deleted_at`");
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `sync_status`");
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `device_id`");

            $indexName = "idx_{$table}_updated_at";
            DB::statement("DROP INDEX IF EXISTS `{$indexName}` ON `{$table}`");
        }
    }
};
