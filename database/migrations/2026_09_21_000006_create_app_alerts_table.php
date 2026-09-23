<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('estudiante_id');
            $table->unsignedBigInteger('curso_id');
            $table->enum('tipo', [
                'faltas_consecutivas',
                'bajo_rendimiento',
                'baja_participacion',
                'tareas_no_entregadas',
                'dificultades_multiples',
            ]);
            $table->text('descripcion');
            $table->date('fecha');
            $table->boolean('leida')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_at', 30)->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();

            $table->index('usuario_id');
            $table->index('estudiante_id');
            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_alerts');
    }
};