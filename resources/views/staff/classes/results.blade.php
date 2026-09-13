@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('staff.classes.index') }}" class="text-sm font-semibold text-slate-500">← Manajemen Kelas</a>
            <h1 class="mt-4 text-3xl font-black">Hasil Belajar</h1>
            <p class="mt-2 text-slate-500">
                {{ $class['school'] }} · {{ $class['major'] }} · {{ $class['grade'] }} {{ $class['parallel'] }}
            </p>
        </div>
        <a href="{{ route('staff.classes.results.export', $class) }}"
            style="display:inline-flex;align-items:center;background:#d97706;color:#fff;font-weight:700;padding:12px 18px;border-radius:14px;text-decoration:none;">
            Export Excel
        </a>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Siswa', $analysis['total']],
            ['Rata-rata XP', $analysis['avg_xp'] ?? '-'],
            ['Pretest', $analysis['avg_pretest'] ?? '-'],
            ['Posttest', $analysis['avg_posttest'] ?? '-'],
        ] as [$label, $value])
            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-black">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Listening', $analysis['avg_listening']],
            ['Reading', $analysis['avg_reading']],
            ['Writing', $analysis['avg_writing']],
            ['Speaking', $analysis['avg_speaking']],
            ['Kenaikan Pre→Post', $analysis['gain']],
        ] as [$label, $value])
            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ $value ?? '-' }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-8 rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-black">Analisis kelas</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-slate-500">Belum ada nilai</p>
                <p class="text-2xl font-black">{{ $analysis['bands']['belum'] }} siswa</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Berkembang (&lt; 70)</p>
                <p class="text-2xl font-black">{{ $analysis['bands']['berkembang'] }} siswa</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Mahir (≥ 70)</p>
                <p class="text-2xl font-black">{{ $analysis['bands']['mahir'] }} siswa</p>
            </div>
        </div>
        <p class="mt-4 text-sm text-slate-500">
            Kategori memakai rata-rata skor listening, reading, writing, dan speaking yang sudah selesai.
            Kenaikan Pre→Post membandingkan rata-rata pretest dan posttest kelas.
        </p>
    </div>

    <div class="overflow-x-auto rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 font-bold text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">XP</th>
                    <th class="px-4 py-3">L</th>
                    <th class="px-4 py-3">R</th>
                    <th class="px-4 py-3">W</th>
                    <th class="px-4 py-3">S</th>
                    <th class="px-4 py-3">Pre</th>
                    <th class="px-4 py-3">Post</th>
                    <th class="px-4 py-3">Selesai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3 font-semibold">{{ $row['name'] }}</td>
                        <td class="px-4 py-3">{{ $row['xp'] }}</td>
                        <td class="px-4 py-3">{{ $row['listening'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['reading'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['writing'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['speaking'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['pretest_avg'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['posttest_avg'] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $row['completed_lessons'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-500">Belum ada siswa di kelas ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
