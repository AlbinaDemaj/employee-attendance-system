<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');

            $table->enum('status', [
                'present',
                'late',
                'absent',
                'left_early',
                'sick_leave',
                'annual_leave',
                'day_off',
                'business_trip',
            ])->default('absent')->index();

            $table->time('expected_start_time')->nullable();
            $table->dateTime('checkin_time')->nullable();
            $table->dateTime('checkout_time')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);

            // Nga cila IP u bë check-in/check-out - për gjurmë dhe kontroll.
            $table->string('checkin_ip', 45)->nullable();
            $table->string('checkout_ip', 45)->nullable();

            $table->string('reason_category')->nullable();
            $table->text('reason_note')->nullable();
            $table->boolean('excused')->default(false);
            $table->dateTime('reported_at')->nullable();

            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index('work_date');
            $table->index(['business_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
