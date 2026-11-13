<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class FixSmkN5Pplg306307 extends Command
{
    protected $signature = 'fix:smkn5-pplg-collision';

    protected $description = 'Buat akun baru untuk 2 siswa PPLG SMK N 5 Surakarta yang akunnya ketimpa siswa SMK N 2 Surakarta';

    public function handle(): int
    {
        $students = [
            [
                'name' => "Muhammad Fu'Ad",
                'email' => 'fuad.pplg5.6374@speakverse.id',
                'password' => 'beymt852',
            ],
            [
                'name' => 'Muhammad Nauval Pangestu',
                'email' => 'nauval.pplg5.8473@speakverse.id',
                'password' => 'wrtvt477',
            ],
        ];

        foreach ($students as $s) {
            if (User::where('email', $s['email'])->exists()) {
                $this->error('Email sudah dipakai, dibatalkan: '.$s['email']);

                continue;
            }

            $user = new User([
                'name' => $s['name'],
                'email' => $s['email'],
                'school' => 'SMK N 5 Surakarta',
                'major' => 'Pengembangan Perangkat Lunak dan Gim',
                'grade' => 'X',
                'parallel' => 'A',
            ]);
            $user->password = Hash::make($s['password']);
            $user->email_verified_at = now();
            $user->role = 'student';
            $user->save();

            $this->info("Dibuat: id={$user->id} {$s['name']} <{$s['email']}>");
        }

        return self::SUCCESS;
    }
}
