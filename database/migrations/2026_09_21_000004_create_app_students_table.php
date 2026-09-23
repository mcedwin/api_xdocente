<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_students', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('curso_id');
            $table->string('nombre');
            $table->text('notas')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_at', 30)->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();

            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_students');
    }
};