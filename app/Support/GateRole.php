<?php

namespace App\Support;

/**
 * Peran akun. Angkanya mengikuti tabel roles: 1 = superadmin, 2 = admin, 3 = viewer.
 */
final class GateRole
{
    public const SUPERADMIN = 1;
    public const ADMIN      = 2;
    public const VIEWER     = 3;

    public const LABELS = [
        self::SUPERADMIN => 'Super Admin',
        self::ADMIN      => 'Admin',
        self::VIEWER     => 'Viewer',
    ];

    private const SLUGS = [
        self::SUPERADMIN => 'superadmin',
        self::ADMIN      => 'admin',
        self::VIEWER     => 'viewer',
    ];

    public static function slug($id): ?string
    {
        return self::SLUGS[(int) $id] ?? null;
    }

    public static function label($id): ?string
    {
        return self::LABELS[(int) $id] ?? null;
    }

    public static function idFromSlug(string $slug): ?int
    {
        $id = array_search($slug, self::SLUGS, true);

        return $id === false ? null : $id;
    }
}