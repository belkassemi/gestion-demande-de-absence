<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD v2.0 — Services table
 * Must run BEFORE update_users_table (which adds service_id FK).
 * File named 000000b so it executes after departments (000000) but before users update (000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            // chef_service_id = the User (chef_service role) who heads this service
            $table->unsignedBigInteger('chef_service_id')->nullable();
            $table->timestamps();

            $table->index('department_id');
            $table->index('chef_service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
