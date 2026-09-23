<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice suelto sobre estudiante_id en app_activity_group_overrides.
 *
 * La tabla solo tenía el índice compuesto (grupo_id, estudiante_id), que no
 * sirve para las consultas por estudiante solo (DELETE/UPDATE por estudiante
 * al eliminar/borrar un estudiante del curso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_activity_group_overrides', function (Blueprint $table) {
            $table->index('estudiante_id', 'idx_overrides_estudiante');
        });
    }

    public function down(): void
    {
        Schema::table('app_activity_group_overrides', function (Blueprint $table) {
            $table->dropIndex('idx_overrides_estudiante');
        });
    }
};