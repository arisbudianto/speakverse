@extends('layouts.admin')

@section('content')
    @php
        $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $num = fn ($v, $d = 2) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
    @endphp

    <div class="space-y-8">
        @include('admin.learning-analytics._tabs')
        <div>
            <h1 class="text-3xl font-black">Analitik Lanjutan · Penguasaan Kompetensi</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Bayesian Knowledge Tracing per kompetensi, kurva belajar, dan siswa yang berputar-putar tanpa menguasai.</p>
        </div>

        @if (! $ready)
            <div class="{{ $card }}">
                <p class="font-bold">Model penguasaan belum dihitung.</p>
                <p class="mt-2 text-sm text-slate-500">Jalankan <code>php artisan migrate</code> lalu <code>php artisan la:fit-models</code>. Butuh log kuis (learning_events) yang sudah dinilai.</p>
            </div>
        @else
            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Kompetensi</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr><th class="py-2">Kompetensi</th><th>Siswa</th><th>Pengamatan</th><th>L0</th><th>T</th><th>S</th><th>G</th><th>Menguasai</th><th>Berputar-putar</th><th>Kurva belajar</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($comps as $c)
                                <tr>
                                    <td class="py-2 font-bold">{{ $c->competency }}@if ($c->is_default) <span class="block text-[10px] font-normal text-yellow-600">parameter bawaan (data sedikit)</span>@endif</td>
                                    <td>{{ $c->students }}</td>
                                    <td>{{ $c->n_obs }}</td>
                                    <td>{{ $num($c->l0) }}</td><td>{{ $num($c->t) }}</td><td>{{ $num($c->s) }}</td><td>{{ $num($c->g) }}</td>
                                    <td>{{ $c->mastered }} ({{ $c->students > 0 ? round($c->mastered * 100 / $c->students) : 0 }}%)</td>
                                    <td class="{{ $c->wheel_spinning > 0 ? 'font-bold text-red-500' : '' }}">{{ $c->wheel_spinning }}</td>
                                    <td>
                                        @php $pts = $c->curve['points'] ?? []; @endphp
                                        @if (count($pts) >= 2)
                                            <svg viewBox="0 0 80 24" class="h-6 w-20" role="img" aria-label="Kurva kesalahan per kesempatan">
                                                @foreach ($pts as $i => $pt)
                                                    @if ($i > 0)
                                                        <line x1="{{ 2 + ($i - 1) * (76 / max(1, count($pts) - 1)) }}" y1="{{ 22 - $pts[$i - 1]['error'] * 20 }}" x2="{{ 2 + $i * (76 / max(1, count($pts) - 1)) }}" y2="{{ 22 - $pt['error'] * 20 }}" stroke="#8b5cf6" stroke-width="1.5" />
                                                    @endif
                                                @endforeach
                                            </svg>
                                            <span class="text-[10px] text-slate-500">b = {{ $num($c->curve['b'] ?? null) }}</span>
                                        @else – @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-500">Dihitung: {{ $computed }}. Kurva = tingkat kesalahan per kesempatan ke-1, 2, 3, …; b &lt; 0 berarti siswa belajar dari latihan (hukum pangkat).</p>
            </div>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Berputar-putar tanpa menguasai (wheel spinning)</h2>
                @if ($wheel->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">Tidak ada siswa yang memenuhi kriteria.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Nama</th><th>Sekolah</th><th>Kelas</th><th>Kompetensi</th><th>Percobaan</th><th>P(menguasai)</th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                @foreach ($wheel as $w)
                                    <tr>
                                        <td class="py-2 font-bold">{{ $w->name }}</td><td>{{ $w->school }}</td><td>{{ $w->grade }} {{ $w->major }} {{ $w->parallel }}</td>
                                        <td>{{ $w->competency }}</td><td>{{ $w->n_obs }}</td><td>{{ $num($w->p_mastery) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-3 text-xs text-slate-500">Kriteria: ≥10 percobaan pertama pada kompetensi itu dan P(menguasai) &lt; 0,95. Ini penanda untuk dicek guru, bukan vonis.</p>
            </div>

            <details class="{{ $card }}">
                <summary class="cursor-pointer text-lg font-black">Definisi dan keterbatasan</summary>
                <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p><b>BKT</b> (Corbett &amp; Anderson, 1995): L0 = peluang sudah menguasai di awal, T = peluang belajar per kesempatan, S = slip, G = tebakan benar. P(menguasai) diperbarui tiap jawaban percobaan pertama. Parameter dicari dengan grid terhadap log-likelihood gabungan semua siswa, dengan S ≤ 0,30 dan G ≤ 0,30.</p>
                    <p>Menguasai: P ≥ 0,95. Wheel spinning (Beck &amp; Gong, 2013): ≥10 percobaan tanpa penguasaan. Kompetensi = sub-keterampilan soal (Reading/Listening) atau kategori (Vocabulary).</p>
                    <p>Keterbatasan: hanya percobaan pertama yang dipakai; soal dalam satu kompetensi diasumsikan setara; kompetensi dengan &lt;30 pengamatan atau &lt;5 siswa memakai parameter bawaan sehingga hasilnya lemah; log hanya mencakup Reading, Listening, Vocabulary.</p>
                </div>
            </details>
        @endif
    </div>
@endsection
