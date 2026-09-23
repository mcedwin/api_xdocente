<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesos (porcentajes) para el cálculo configurable de la nota final:
     * asistencia, tareas, prácticas, participación y proyecto.
     * Valor por defecto 20 (reparto equitativo; suma 100).
     */
    public function up(): void
    {
        Schema::table('app_courses', function (Blueprint $table) {
            $table->decimal('peso_asistencia', 5, 2)->nullable()->default(20);
            $table->decimal('peso_tareas', 5, 2)->nullable()->default(20);
            $table->decimal('peso_practicas', 5, 2)->nullable()->default(20);
            $table->decimal('peso_participacion', 5, 2)->nullable()->default(20);
            $table->decimal('peso_proyecto', 5, 2)->nullable()->default(20);
        });
    }

    public function down(): void
    {
        Schema::table('app_courses', function (Blueprint $table) {
            $table->dropColumn([
                'peso_asistencia',
                'peso_tareas',
                'peso_practicas',
                'peso_participacion',
                'peso_proyecto',
            ]);
        });
    }
};