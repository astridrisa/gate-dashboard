<?php

namespace App\Http\Middleware;

use App\Support\GateRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses route berdasarkan peran akun (kolom users.role_id).
 * Pemakaian di route: EnsureRole::class . ':admin,superadmin'
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_filter(array_map(fn ($r) => GateRole::idFromSlug($r), $roles));
        $user = $request->user();

        if (! $user || ! in_array((int) $user->role_id, $allowed, true)) {
            abort(403, 'Kamu tidak punya akses ke halaman atau aksi ini.');
        }

        return $next($request);
    }
}