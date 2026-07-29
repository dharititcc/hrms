<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends attendance with shifts, breaks, geolocation, device capture and
 * approval, and adds the office locations a geofence is measured against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['owner_id', 'name']);
        });

        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            // 7 decimal places is roughly centimetre precision, far beyond
            // what browser geolocation provides.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_metres')->default(200);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('work_shift_id')->nullable()->after('staff_id')->constrained()->nullOnDelete();
            $table->string('work_mode', 20)->default('office')->after('status');

            // Derived on check-out, stored so reports do not recompute them.
            $table->unsignedInteger('worked_minutes')->default(0)->after('work_mode');
            $table->unsignedInteger('break_minutes')->default(0)->after('worked_minutes');
            $table->unsignedInteger('late_minutes')->default(0)->after('break_minutes');
            $table->unsignedInteger('overtime_minutes')->default(0)->after('late_minutes');

            // Where and how each half of the day was recorded. Check-in and
            // check-out can happen in different places.
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->string('check_in_address')->nullable();
            $table->foreignId('check_in_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();

            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->string('check_out_address')->nullable();

            $table->string('device_type', 40)->nullable();
            $table->string('device_os', 60)->nullable();
            $table->string('device_browser', 60)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Attendance recorded outside the geofence, or entered by hand, is
            // held until somebody approves it.
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->index(['owner_id', 'work_date', 'status'], 'attendances_owner_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_owner_date_status_idx');
            $table->dropConstrainedForeignId('work_shift_id');
            $table->dropConstrainedForeignId('check_in_location_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'work_mode', 'worked_minutes', 'break_minutes', 'late_minutes', 'overtime_minutes',
                'check_in_latitude', 'check_in_longitude', 'check_in_address',
                'check_out_latitude', 'check_out_longitude', 'check_out_address',
                'device_type', 'device_os', 'device_browser', 'ip_address',
                'requires_approval', 'is_manual', 'approved_at',
            ]);
        });

        Schema::dropIfExists('attendance_locations');
        Schema::dropIfExists('work_shifts');
    }
};
