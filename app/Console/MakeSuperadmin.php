<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GateRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeSuperadmin extends Command
{
    protected $signature = 'gate:make-superadmin {email : Email akun} {--name= : Nama tampilan (untuk akun baru)}';

    protected $description = 'Buat akun superadmin baru, atau jadikan akun yang sudah ada sebagai superadmin';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi (min 8 karakter)');
        if (strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');
            return self::FAILURE;
        }
        if ($password !== (string) $this->secret('Ulangi kata sandi')) {
            $this->error('Kata sandi tidak sama.');
            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        $baru = $user === null;
        if ($baru) {
            $user = new User();
            $user->email = $email;
            $user->name  = $this->option('name') ?: 'Super Admin';
        }

        $user->forceFill(['password' => Hash::make($password), 'role_id' => GateRole::SUPERADMIN])->save();

        $this->info(($baru ? 'Superadmin dibuat: ' : 'Akun dijadikan superadmin: ') . $email);
        return self::SUCCESS;
    }
}