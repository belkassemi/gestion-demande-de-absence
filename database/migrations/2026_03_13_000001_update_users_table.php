<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->change();
            $table->enum('role', ['employee', 'manager', 'hr', 'admin'])->default('employee')->after('email');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('role');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['role', 'department_id', 'manager_id']);
        });
    }
};
