@extends('layouts.admin')

@section('content')
    @php
        $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $n = fn ($v, $d = 2) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
        $pv = fn ($p) => $p === null ? '–' : ($p < 0.001 ? '< 0,001' : number_format($p, 3, ',', ''));
    @endphp

    <div class="space-y-8">
        @include('admin.learning-analytics._tabs')
        <div>
            <h1 class="text-3xl font-black">Analitik Lanjutan · Intervensi &amp; Efektivitas Konten</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Apakah tindak lanjut guru diikuti perubahan aktivitas, dan pelajaran mana yang tampak membantu. Ini asosiasi, bukan bukti sebab-akibat.</p>
        </div>

        @if (! $ready)
            <div class="{{ $card }}"><p class="font-bold">Tabel intervensi belum ada.</p><p class="mt-2 text-sm text-slate-500">Jalankan <code>php artisan migrate</code>.</p></div>
        @else
            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Perubahan aktivitas setelah tindak lanjut</h2>
                <p class="mt-1 text-xs text-slate-500">Hari aktif dalam {{ $eval['window_days'] ?? 14 }} hari sebelum vs sesudah. Pembanding: siswa berisiko yang belum ditindaklanjuti (selisih-dalam-selisih, uji Welch).</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-4 text-sm">
                    <div><b>Ditindaklanjuti</b><br>{{ $eval['followed'] }} siswa · Δ {{ $n($eval['mean_change_followed']) }} hari</div>
                    <div><b>Pembanding</b><br>{{ $eval['not_followed'] }} siswa · Δ {{ $n($eval['mean_change_control']) }} hari</div>
                    @if ($eval['ready'])
                        <div><b>Selisih-dalam-selisih</b><br>{{ $n($eval['did']['diff']) }} hari (CI95 {{ $n($eval['did']['ci'][0]) }} s.d. {{ $n($eval['did']['ci'][1]) }}), p = {{ $pv($eval['did']['p']) }}</div>
                        <div><b>Ukuran efek (Hedges g)</b><br>{{ $n($eval['g']) }}</div>
                    @endif
                </div>
                @unless ($eval['ready'])<p class="mt-4 text-sm text-slate-500">{{ $eval['reason'] }}</p>@endunless
            </div>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Tindak lanjut terbaru</h2>
                <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Siswa</th><th>Guru</th><th>Risiko</th><th>Tindakan</th><th>Catatan</th><th>Waktu</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($recent as $r)<tr><td class="py-2 font-bold">{{ $r->name }}</td><td>{{ $r->teacher ?? '–' }}</td><td>{{ $r->risk_level ?? '–' }}</td><td>{{ str_replace('_', ' ', $r->action) }}</td><td>{{ $r->note }}</td><td>{{ \Illuminate\Support\Carbon::parse($r->followed_at)->format('d/m/Y H:i') }}</td></tr>
                    @empty<tr><td colspan="6" class="py-4 text-slate-500">Belum ada tindak lanjut tercatat.</td></tr>@endforelse
                    </tbody></table></div>
            </div>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Efektivitas pelajaran</h2>
                <p class="mt-1 text-xs text-slate-500">Uplift = selisih rerata gain (post−pre) siswa yang menyelesaikan pelajaran vs belum; dihitung bila tiap kelompok ≥ 8 siswa.</p>
                <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Pelajaran</th><th>Mulai</th><th>Ditinggalkan</th><th>Akurasi pertama</th><th>Selesai / belum</th><th>Uplift (CI95)</th><th>g</th><th>Penanda</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($content as $c)
                        <tr><td class="py-2 font-bold">{{ $c['name'] }}</td><td>{{ $c['starts'] }}</td><td>{{ $c['abandon_rate'] === null ? '–' : round($c['abandon_rate'] * 100) . '%' }}</td>
                            <td>{{ $c['first_try_acc'] === null ? '–' : round($c['first_try_acc'] * 100) . '%' }}</td><td>{{ $c['n_done'] }} / {{ $c['n_not'] }}</td>
                            <td>{{ $c['uplift'] === null ? '–' : $n($c['uplift'], 1) . ' (' . $n($c['uplift_ci'][0], 1) . '; ' . $n($c['uplift_ci'][1], 1) . ')' }}</td><td>{{ $n($c['g']) }}</td><td>{{ implode(', ', $c['flags']) }}</td></tr>
                    @empty<tr><td colspan="8" class="py-4 text-slate-500">Belum ada data pelajaran.</td></tr>@endforelse
                    </tbody></table></div>
            </div>
        @endif
    </div>
@endsection
