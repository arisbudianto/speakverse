<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * Route yang tetap boleh diakses walau profil belum lengkap,
     * supaya user tidak terjebak dalam redirect loop dan tetap bisa
     * melengkapi profil atau logout.
     *
     * Sama pola dengan ForcePasswordChange::ALLOWED_ROUTES.
     */
    private const ALLOWED_ROUTES = [
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'password.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
    ];

    /**
     * Handle an incoming request.
     *
     * Kasus yang ditutup: user daftar lewat "Continue with Google"
     * (GoogleAuthController) sehingga akun langsung terbuat tanpa
     * melalui form register manual yang mewajibkan sekolah/jurusan/
     * kelas/paralel. Tanpa data ini, user tidak akan muncul di
     * roster kelas guru maupun matriks analisis per sekolah/jurusan.
     *
     * SENGAJA dibatasi hanya untuk akun yang google_id-nya terisi
     * (benar-benar daftar/connect lewat Google). Akun lama/bulk-import
     * oleh admin yang kolom sekolah/jurusan-nya kosong (Admin\UserController
     * mengizinkan nullable) TIDAK disentuh middleware ini, supaya siswa
     * yang sedang berjalan di gelombang data collection tidak mendadak
     * terkunci dari akunnya sendiri.
     *
     * admin tidak butuh data ini sama sekali.
     * teacher hanya wajib "school".
     * student (dan role lain yang bukan admin/teacher) wajib
     * keempatnya: school, major, grade, parallel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (
            $user
            && filled($user->google_id)
            && ! $request->routeIs(...self::ALLOWED_ROUTES)
        ) {

            $role = $user->role;

            $isIncomplete = match (true) {
                $role === 'admin' => false,
                $role === 'teacher' => blank($user->school),
                default => blank($user->school)
                    || blank($user->major)
                    || blank($user->grade)
                    || blank($user->parallel),
            };

            if ($isIncomplete) {
                return redirect()
                    ->route('profile.edit')
                    ->with(
                        'error',
                        'Lengkapi dulu data sekolah/jurusan/kelas Anda sebelum melanjutkan.'
                    );
            }
        }

        return $next($request);
    }
}
