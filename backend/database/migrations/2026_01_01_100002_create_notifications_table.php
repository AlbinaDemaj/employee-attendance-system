<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // marrësi
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // who the notification is about (employee), when relevant
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->index(); // missing_checkin | late_checkin | leave_requested | leave_decided | info_requested
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();

            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();

            // used to avoid creating the same notification twice (e.g. one per employee per day per type)
            $table->string('dedupe_key')->nullable()->unique();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
