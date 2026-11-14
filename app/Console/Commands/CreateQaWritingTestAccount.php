<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Command sementara untuk QA manual: membuat satu akun siswa dummy
 * (bukan siswa asli, bukan bagian dari roster sekolah mana pun) supaya
 * bisa dipakai login dan mengecek tampilan layar warming-up baru di
 * pretest writing (lihat commit 52fa0e8).
 *
 * Aman dihapus kapan saja setelah QA selesai: command ini hanya
 * membuat/melaporkan satu baris user, tidak menyentuh data lain.
 */
class CreateQaWritingTestAccount extends Command
{
    protected $signature = 'qa:create-writing-test-account';

    protected $description = 'Membuat satu akun siswa dummy untuk QA manual pretest writing';

    public function handle(): int
    {
        $email = 'qa.writing.test@speakverse.id';
        $password = 'QaTest123!';

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->info("Sudah ada: id={$existing->id} {$existing->email}");

            return self::SUCCESS;
        }

        $user = new User([
            'name' => 'QA Test Writing',
            'email' => $email,
            'password' => Hash::make($password),
            'school' => 'QA Internal Test',
            'major' => 'Testing',
            'grade' => 'X',
            'parallel' => 'A',
        ]);

        $user->role = 'student';
        $user->email_verified_at = now();
        $user->save();

        $this->info("Dibuat: id={$user->id} {$email} / password: {$password}");

        return self::SUCCESS;
    }
}
