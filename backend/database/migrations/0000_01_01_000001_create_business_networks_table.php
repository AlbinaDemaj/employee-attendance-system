<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rrjetet nga të cilat lejohet check-in-i.
 *
 * Shfletuesi nuk mund ta lexojë emrin e WiFi-t (SSID) - asnjë browser nuk e
 * lejon. Prandaj identifikimi i "WiFi-t të punës" bëhet me adresën IP: të
 * gjitha pajisjet e lidhura me të njëjtin WiFi dalin në internet me të njëjtën
 * IP publike. Biznesi regjistron këtu atë IP (ose një rang CIDR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // p.sh. "WiFi - Zyra qendrore"
            $table->string('ip_range'); // "88.99.12.34" ose "192.168.1.0/24"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_networks');
    }
};
