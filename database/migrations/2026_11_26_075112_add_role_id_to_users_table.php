<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lewati kalau tabel users sudah punya kolom role_id
        if (Schema::hasColumn('users', 'role_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // ID dari tabel roles: 1 = superadmin, 2 = admin, 3 = viewer
            $table->unsignedTinyInteger('role_id')->default(3)->after('password');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }
    }
};