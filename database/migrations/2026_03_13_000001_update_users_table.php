<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['employee', 'chef_service', 'directeur', 'admin'])->default('employee')->after('email');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('role');
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete()->after('department_id');
            // chef_service_id: ID of the chef de service managing this user (employees point to their chef)
            $table->foreignId('chef_service_id')->nullable()->constrained('users')->nullOnDelete()->after('service_id');
            $table->boolean('is_active')->default(true)->after('chef_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['chef_service_id']);
            $table->dropColumn(['role', 'department_id', 'service_id', 'chef_service_id', 'is_active']);
        });
    }
};
