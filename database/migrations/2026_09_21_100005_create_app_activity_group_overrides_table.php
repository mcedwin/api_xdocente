<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_activity_group_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('estudiante_id');
            $table->unsignedBigInteger('criterio_id')->nullable();
            $table->decimal('puntaje', 5, 2);
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('deleted_at', 30)->nullable();

            $table->index(['grupo_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_activity_group_overrides');
    }
};
