<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lewati kalau tabel roles sudah ada
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();   // 1, 2, 3 (diisi manual oleh seeder)
            $table->string('name', 30)->unique();           // superadmin | admin | viewer
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};