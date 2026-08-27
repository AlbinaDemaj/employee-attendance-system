<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // sick_leave / annual_leave / day_off / business_trip / unpaid
            $table->enum('type', [
                'sick_leave',
                'annual_leave',
                'day_off',
                'business_trip',
                'unpaid',
            ])->index();

            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('certificate_path')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'info_requested'])
                ->default('pending')
                ->index();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('manager_note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
