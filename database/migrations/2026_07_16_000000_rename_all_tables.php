<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── System tables (Laravel) → sys_ prefix ──
        $sysRenames = [
            'sessions'                  => 'sys_sessions',
            'cache'                     => 'sys_cache',
            'cache_locks'               => 'sys_cache_locks',
            'jobs'                      => 'sys_jobs',
            'job_batches'               => 'sys_job_batches',
            'failed_jobs'               => 'sys_failed_jobs',
            'personal_access_tokens'    => 'sys_personal_access_tokens',
            'password_reset_tokens'     => 'sys_password_reset_tokens',
            'migrations'                => 'sys_migrations',
        ];

        // ── Business tables → app_ prefix ──
        $appRenames = [
            'usuarios'                      => 'app_users',
            'cursos'                        => 'app_courses',
            'estudiantes'                   => 'app_students',
            'unidades'                      => 'app_units',
            'sesiones'                      => 'app_sessions',
            'registros_asistencia'          => 'app_attendance',
            'tareas'                        => 'app_tasks',
            'calificaciones_tareas'         => 'app_task_grades',
            'practicas'                     => 'app_practices',
            'calificaciones_practicas'      => 'app_practice_grades',
            'items_participacion'           => 'app_participation_items',
            'calificaciones_participacion'  => 'app_participation_grades',
            'trabajos_grupales'             => 'app_group_works',
            'criterios_trabajo_grupal'      => 'app_group_work_criteria',
            'grupos'                        => 'app_groups',
            'integrantes_grupo'             => 'app_group_members',
            'puntajes_criterio_grupo'       => 'app_group_criterion_scores',
            'ajustes_individuales_grupo'    => 'app_individual_adjustments',
            'proyectos'                     => 'app_projects',
            'criterios_proyecto'            => 'app_project_criteria',
            'calificaciones_proyecto'       => 'app_project_grades',
            'alertas'                       => 'app_alerts',
        ];

        $allRenames = array_merge($sysRenames, $appRenames);

        foreach ($allRenames as $old => $new) {
            if (DB::getSchemaBuilder()->hasTable($old) && !DB::getSchemaBuilder()->hasTable($new)) {
                DB::statement("RENAME TABLE `{$old}` TO `{$new}`");
            }
        }
    }

    public function down(): void
    {
        $sysRenames = [
            'sys_sessions'              => 'sessions',
            'sys_cache'                 => 'cache',
            'sys_cache_locks'           => 'cache_locks',
            'sys_jobs'                  => 'jobs',
            'sys_job_batches'           => 'job_batches',
            'sys_failed_jobs'           => 'failed_jobs',
            'sys_personal_access_tokens'=> 'personal_access_tokens',
            'sys_password_reset_tokens' => 'password_reset_tokens',
            'sys_migrations'            => 'migrations',
        ];

        $appRenames = [
            'app_users'                     => 'usuarios',
            'app_courses'                   => 'cursos',
            'app_students'                  => 'estudiantes',
            'app_units'                     => 'unidades',
            'app_sessions'                  => 'sesiones',
            'app_attendance'                => 'registros_asistencia',
            'app_tasks'                     => 'tareas',
            'app_task_grades'               => 'calificaciones_tareas',
            'app_practices'                 => 'practicas',
            'app_practice_grades'           => 'calificaciones_practicas',
            'app_participation_items'       => 'items_participacion',
            'app_participation_grades'      => 'calificaciones_participacion',
            'app_group_works'               => 'trabajos_grupales',
            'app_group_work_criteria'       => 'criterios_trabajo_grupal',
            'app_groups'                    => 'grupos',
            'app_group_members'             => 'integrantes_grupo',
            'app_group_criterion_scores'    => 'puntajes_criterio_grupo',
            'app_individual_adjustments'    => 'ajustes_individuales_grupo',
            'app_projects'                  => 'proyectos',
            'app_project_criteria'          => 'criterios_proyecto',
            'app_project_grades'            => 'calificaciones_proyecto',
            'app_alerts'                    => 'alertas',
        ];

        $allRenames = array_merge($sysRenames, $appRenames);

        foreach ($allRenames as $old => $new) {
            if (DB::getSchemaBuilder()->hasTable($old) && !DB::getSchemaBuilder()->hasTable($new)) {
                DB::statement("RENAME TABLE `{$old}` TO `{$new}`");
            }
        }
    }
};
