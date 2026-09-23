<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_activities', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('unidad_id');
            $table->string('type', 20); // task, practice, participation, group_work, project
            $table->string('nombre');
            $table->date('fecha')->nullable();
            $table->boolean('is_group_based')->default(false);
            $table->boolean('uses_rubric')->default(false);
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('deleted_at', 30)->nullable();

            $table->index(['unidad_id', 'type']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_activities');
    }
};
