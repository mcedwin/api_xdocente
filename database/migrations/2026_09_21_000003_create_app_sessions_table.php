<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('unidad_id');
            $table->date('fecha');
            $table->string('tema', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_at', 30)->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();

            $table->index('unidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_sessions');
    }
};