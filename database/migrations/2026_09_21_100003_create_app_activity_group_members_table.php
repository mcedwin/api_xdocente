<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_activity_group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('estudiante_id');

            $table->primary(['grupo_id', 'estudiante_id']);
            $table->index('estudiante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_activity_group_members');
    }
};
