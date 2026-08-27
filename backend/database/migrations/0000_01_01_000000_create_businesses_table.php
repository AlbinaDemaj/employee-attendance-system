<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Një "business" është një klient i produktit. Super-admini i krijon këto
 * llogari; brenda çdo biznesi, admini i tij menaxhon vetë menaxherët,
 * punonjësit, orarin dhe rrjetet e lejuara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('Europe/Tirane');

            // Orari standard i biznesit - punonjësit e trashëgojnë si parazgjedhje.
            $table->time('default_start_time')->default('08:00:00');
            $table->time('default_end_time')->default('16:00:00');
            $table->json('working_days')->nullable(); // [1..7], 1 = e hënë

            // Rregullat e prezencës, të konfigurueshme për çdo biznes.
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);
            $table->unsignedSmallInteger('manager_alert_after_minutes')->default(15);
            $table->boolean('auto_approve_sick_with_certificate')->default(false);

            // Check-in vetëm nga rrjeti i punës (shih business_networks).
            $table->boolean('require_network_for_checkin')->default(true);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
