<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('tap_time');
            $table->string('area_name')->nullable();
            $table->string('device_name');
            $table->string('direction', 20);               // Masuk / Keluar / Lainnya
            $table->string('event_point')->nullable();
            $table->string('event_description');
            $table->string('event_level')->nullable();
            $table->string('personnel_id', 50)->nullable();
            $table->string('full_name')->nullable();
            $table->string('card_number', 50)->nullable();
            $table->string('department_name')->default('Tanpa Departemen');
            $table->string('reader_name')->nullable();
            $table->string('verification_mode', 50)->nullable();
            $table->timestamps();

            // Hanya indeks yang dipakai dashboard
            $table->index(['tap_time', 'direction']);
            $table->index('device_name');
            $table->index('department_name');
            $table->index('personnel_id');
            $table->index('event_description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_logs');
    }
};