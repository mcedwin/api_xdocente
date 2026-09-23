<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->nullable()->unique();
            $table->string('firebase_uid', 255)->nullable();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('avatar', 255)->nullable();
            $table->string('institucion', 255)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('password', 255)->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('updated_at', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};