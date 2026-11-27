<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\GateRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Manajemen akun. Hanya bisa diakses superadmin (diatur di routes). */
class UserController extends Controller
{
    private const ATTRIBUTES = [
        'name' => 'nama', 'email' => 'email', 'role_id' => 'peran', 'password' => 'kata sandi',
    ];

    private function roleRule(): string
    {
        return 'in:' . implode(',', array_keys(GateRole::LABELS));
    }

    public function index()
    {
        return view('users', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id'  => ['required', 'integer', $this->roleRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], self::ATTRIBUTES);

        $user = new User();
        $user->forceFill([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'role_id'  => (int) $data['role_id'],
            'password' => Hash::make($data['password']),
        ])->save();

        return back()->with('success', "Akun {$user->email} berhasil dibuat.");
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', $this->roleRule()],
        ], [], self::ATTRIBUTES);

        $newRole = (int) $data['role_id'];

        if ((int) $user->role_id === GateRole::SUPERADMIN && $newRole !== GateRole::SUPERADMIN && $this->isLastSuperadmin()) {
            return back()->withErrors(['role_id' => 'Tidak bisa menurunkan superadmin terakhir.']);
        }

        $user->forceFill(['role_id' => $newRole])->save();

        return back()->with('success', "Peran {$user->email} diubah menjadi " . GateRole::label($newRole) . '.');
    }

    public function updatePassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [], self::ATTRIBUTES);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('success', "Kata sandi {$user->email} berhasil direset.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['role_id' => 'Kamu tidak bisa menghapus akunmu sendiri.']);
        }
        if ((int) $user->role_id === GateRole::SUPERADMIN && $this->isLastSuperadmin()) {
            return back()->withErrors(['role_id' => 'Tidak bisa menghapus superadmin terakhir.']);
        }

        $user->delete();

        return back()->with('success', "Akun {$user->email} dihapus.");
    }

    private function isLastSuperadmin(): bool
    {
        return User::query()->where('role_id', GateRole::SUPERADMIN)->count() <= 1;
    }
}