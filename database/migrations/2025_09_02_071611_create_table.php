<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('provider_email')->nullable();
            $table->string('avatar')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('job_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('employment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('tolerance_come_too_late')->default(10);
            $table->integer('tolerance_go_home_early')->default(5);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->jsonb('schedule_data')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'month', 'year']);
        });

        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_deduct_quota')->default(false)->after('name');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained('job_levels')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->nullOnDelete();
            $table->enum('work_scheme', ['office', 'shift'])->default('shift');
            $table->string('employee_id');
            $table->string('nip')->unique();
            $table->date('join_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('photo')->nullable();
            $table->text('avatar')->nullable();

            $table->text('attachment')->nullable();

            $table->boolean('is_ppa')->default(false);
            $table->string('group')->nullable();
            $table->string('rank')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('religion')->nullable();
            $table->string('phone')->nullable();
            $table->string('nik')->nullable();
            $table->string('npwp')->unique();
            $table->string('postal_code')->nullable();
            $table->text('address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->nullable()->constrained('personals')->nullOnDelete();
            $table->foreignId('relationship_id')->nullable()->constrained('relationships')->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('latitude');
            $table->string('longitude');
            $table->integer('radius')->default(100);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status')->default('pending');
            $table->boolean('is_location_valid')->default(false);
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->text('rejection_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->string('attendance_type');
            $table->time('time');
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('photo')->nullable();
            $table->string('device_info')->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->year('year');
            $table->integer('total_quota')->default(12);
            $table->integer('used_quota')->default(0);
            $table->timestamps();
        });

        Schema::create('religions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('marital_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_off_id')->constrained('time_offs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->text('file')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->text('rejection_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('attendance_type');
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->text('reason')->nullable();
            $table->text('file')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->text('rejection_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('change_shift_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_old_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('shift_new_id')->constrained('shifts')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->text('rejection_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('date');
            $table->string('duration_before')->nullable();
            $table->string('rest_duration_before')->nullable();
            $table->string('duration_after')->nullable();
            $table->string('rest_duration_after')->nullable();
            $table->text('reason')->nullable();
            $table->text('file')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->text('rejection_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('approval_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->integer('step');
            $table->timestamps();
        });

        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->integer('step');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('action_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
        Schema::dropIfExists('approval_lines');
        Schema::dropIfExists('marital_statuses');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('change_shift_requests');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('time_off_requests');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_locations');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('personals');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('time_offs');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employment_statuses');
        Schema::dropIfExists('job_levels');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('units');
        Schema::dropIfExists('social_accounts');
    }
};
