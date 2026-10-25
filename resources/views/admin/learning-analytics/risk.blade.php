@extends('layouts.admin')

@section('content')
    @php
        $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $input = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-900';
        $badge = ['tinggi' => 'bg-red-100 text-red-700', 'sedang' => 'bg-yellow-100 text-yellow-800', 'rendah' => 'bg-green-100 text-green-700'];
        $pc = fn ($v) => $v === null ? '–' : round($v * 100) . '%';
    @endphp

    <div class="space-y-8">
        @include('admin.learning-analytics._tabs')
        <div>
            <h1 class="text-3xl font-black">Analitik Lanjutan · Peringatan Dini</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Daftar siswa yang <b>perlu dicek</b> beserta alasannya. Skor adalah penanda untuk memulai percakapan, bukan label atau penilaian siswa.</p>
        </div>

        @if (session('status'))<div class="rounded-2xl bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('status') }}</div>@endif

        @if (! $ready)
            <div class="{{ $card }}">
                <p class="font-bold">Skor risiko belum dihitung.</p>
                <p class="mt-2 text-sm text-slate-500">Jalankan <code>php artisan migrate</code> lalu <code>php artisan la:score-risk</code>.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['tinggi' => 'Perlu dicek segera', 'sedang' => 'Perlu dipantau', 'rendah' => 'Aman'] as $lv => $lb)
                    <div class="{{ $card }}"><p class="text-sm font-semibold text-slate-500">{{ $lb }}</p><p class="mt-2 text-4xl font-black">{{ (int) ($counts[$lv] ?? 0) }}</p><p class="mt-2 text-xs text-slate-500">skor {{ $lv === 'tinggi' ? '≥ ' . $thresholds['high'] : ($lv === 'sedang' ? $thresholds['medium'] . '–' . ($thresholds['high'] - 1) : '< ' . $thresholds['medium']) }}</p></div>
                @endforeach
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label class="text-sm font-semibold">Sekolah<br>
                    <select name="school" class="{{ $input }}"><option value="">Semua</option>@foreach ($schools as $s)<option value="{{ $s }}" @selected($filters['school'] === $s)>{{ $s }}</option>@endforeach</select></label>
                <label class="text-sm font-semibold">Tingkat<br>
                    <select name="level" class="{{ $input }}"><option value="">Tinggi &amp; sedang</option>@foreach (['tinggi', 'sedang', 'rendah'] as $l)<option value="{{ $l }}" @selected($filters['level'] === $l)>{{ ucfirst($l) }}</option>@endforeach</select></label>
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-white dark:text-slate-900">Terapkan</button>
            </form>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Siswa yang perlu dicek ({{ $rows->count() }})</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Siswa</th><th>Kelas</th><th>Skor</th><th>Keyakinan data</th><th>Alasan utama &amp; saran</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @forelse ($rows as $r)
                                <tr class="align-top">
                                    <td class="py-3 font-bold">{{ $r->name }}<div class="text-xs font-normal text-slate-500">{{ $r->school }}</div></td>
                                    <td>{{ $r->grade }} {{ $r->major }} {{ $r->parallel }}</td>
                                    <td><span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[$r->level] ?? '' }}">{{ round($r->score) }} · {{ $r->level }}</span></td>
                                    <td>{{ $r->confidence }}</td>
                                    <td>
                                        @foreach ($r->factors as $f)
                                            <div class="mb-1"><b>{{ $f['label'] }}</b> <span class="text-slate-500">— {{ $f['detail'] }}</span><div class="text-xs text-slate-500">→ {{ $f['action'] }}</div></div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-500">Tidak ada siswa pada filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Model prediksi (validasi silang 5-lipat)</h2>
                @if ($model && ! empty($model['ready']))
                    <div class="mt-3 grid gap-3 sm:grid-cols-4 text-sm">
                        <div><b>AUC</b><br>{{ number_format($model['auc'], 2) }}</div>
                        <div><b>Presisi / Recall</b><br>{{ $pc($model['prf']['precision'] ?? null) }} / {{ $pc($model['prf']['recall'] ?? null) }}</div>
                        <div><b>Brier</b><br>{{ number_format($model['brier'], 3) }}</div>
                        <div><b>n (positif)</b><br>{{ $model['n'] }} ({{ $model['positives'] }})</div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Target: skor posttest &lt; {{ $thresholds['post'] }}. Probabilitas model tampil sebagai pelengkap skor aturan.</p>
                    @if (! empty($model['fairness']))
                        <h3 class="mt-5 font-bold">Pemeriksaan keadilan antar sekolah</h3>
                        <table class="mt-2 w-full text-left text-sm"><thead class="text-xs uppercase text-slate-500"><tr><th>Sekolah</th><th>n</th><th>AUC</th><th>Recall</th></tr></thead>
                            <tbody>@foreach ($model['fairness'] as $f)<tr><td class="py-1">{{ $f['school'] }}</td><td>{{ $f['n'] }}</td><td>{{ $f['auc'] === null ? '–' : number_format($f['auc'], 2) }}</td><td>{{ $pc($f['recall']) }}</td></tr>@endforeach</tbody></table>
                        @if (($model['fairness_gap'] ?? null) !== null && $model['fairness_gap'] > 0.1)<p class="mt-2 text-sm font-semibold text-orange-600">Selisih AUC antar sekolah {{ number_format($model['fairness_gap'], 2) }}: gunakan hati-hati.</p>@endif
                    @endif
                @else
                    <p class="mt-3 text-sm text-slate-500">{{ $model['reason'] ?? 'Model belum dilatih (belum ada data posttest yang cukup). Skor berbasis aturan tetap dipakai.' }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.la.risk.weights') }}" class="{{ $card }}">
                @csrf
                <h2 class="text-lg font-black">Bobot skor aturan</h2>
                <p class="mt-1 text-sm text-slate-500">Bobot relatif (0–50). Skor dinormalkan ke 0–100 hanya dari komponen yang punya data.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($weights as $k => $w)
                        <label class="text-sm font-semibold">{{ $labels[$k] ?? $k }}<br><input type="number" step="0.5" min="0" max="50" name="w[{{ $k }}]" value="{{ old('w.' . $k, $w) }}" class="{{ $input }} w-24"> <span class="text-xs font-normal text-slate-500">bawaan {{ $defaults[$k] ?? '' }}</span></label>
                    @endforeach
                </div>
                @if ($errors->any())<p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif
                <button class="mt-4 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-white dark:text-slate-900">Simpan bobot</button>
            </form>
        @endif
    </div>
@endsection
