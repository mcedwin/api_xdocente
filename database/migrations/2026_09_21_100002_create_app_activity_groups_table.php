<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_activity_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('activity_id');
            $table->string('nombre');
            $table->string('sync_status', 20)->default('synced');
            $table->string('device_id', 36)->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('deleted_at', 30)->nullable();

            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_activity_groups');
    }
};
