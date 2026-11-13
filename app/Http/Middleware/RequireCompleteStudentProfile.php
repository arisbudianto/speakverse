<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireCompleteStudentProfile
{
    /**
     * Route yang tetap boleh diakses walau data sekolah/jurusan/kelas/
     * paralel belum lengkap, supaya siswa tidak terjebak dalam redirect
     * loop dan tetap bisa melengkapi profil atau logout.
     *
     * Dipakai terutama untuk siswa yang mendaftar lewat Google, karena
     * GoogleAuthController membuat akun tanpa school/major/grade/
     * parallel (lihat app/Http/Controllers/Auth/GoogleAuthController.php).
     */
    private const ALLOWED_ROUTES = [
        'profile.edit',
        'profile.update',
        'password.update',
        'logout',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (
            $user
            && $user->role === 'student'
            && $this->profileIncomplete($user)
            && ! $request->routeIs(...self::ALLOWED_ROUTES)
        ) {
            return redirect()
                ->route('profile.edit')
                ->with(
                    'error',
                    'Lengkapi data sekolah, jurusan, kelas, dan paralel kamu terlebih dahulu sebelum melanjutkan.'
                );
        }

        return $next($request);
    }

    /**
     * Cek apakah data akademik siswa (sekolah/jurusan/kelas/paralel)
     * masih ada yang kosong.
     */
    private function profileIncomplete($user): bool
    {
        return blank($user->school)
            || blank($user->major)
            || blank($user->grade)
            || blank($user->parallel);
    }
}
