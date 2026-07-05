<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('job_positions', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('level')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number')->nullable()->unique();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('identity_number')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('join_date')->nullable()->index();
            $table->string('employment_status')->default('active')->index();
            $table->text('address')->nullable();
            $table->timestamp('resigned_at')->nullable();
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('contract_number')->nullable()->unique();
            $table->string('contract_type')->default('PKWT')->index();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn([
                'contract_number',
                'contract_type',
                'start_date',
                'end_date',
                'status',
                'notes',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropColumn([
                'employee_number',
                'full_name',
                'email',
                'phone',
                'identity_number',
                'birth_date',
                'join_date',
                'employment_status',
                'address',
                'resigned_at',
            ]);
        });

        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['code', 'name', 'level', 'is_active']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['code', 'name', 'description', 'is_active']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['code', 'name', 'type', 'city', 'province', 'address', 'is_active']);
        });
    }
};
