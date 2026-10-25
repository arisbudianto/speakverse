@extends('layouts.admin')

@section('content')
    @php
        $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $num = fn ($v, $d = 2) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
    @endphp

    <div class="space-y-8">
        @include('admin.learning-analytics._tabs')
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">Analitik Lanjutan · Kualitas Soal</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Analisis butir Teori Tes Klasik: kesukaran, daya beda, distraktor, reliabilitas.</p>
            </div>
            @if ($ready)
                <a href="{{ route('admin.la.items.export') }}" class="rounded-2xl bg-purple-600 px-4 py-2 text-sm font-bold text-white hover:bg-purple-700">Unduh (Excel)</a>
            @endif
        </div>

        @if (! $ready || $forms->isEmpty())
            <div class="{{ $card }}">
                <p class="font-bold">Belum ada hasil analisis butir.</p>
                <p class="mt-2 text-sm text-slate-500">Jalankan <code>php artisan migrate</code> lalu <code>php artisan la:item-analysis</code>. Butuh jawaban pilihan ganda (Reading/Listening/Vocabulary) yang sudah dinilai.</p>
            </div>
        @else
            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Reliabilitas per form tes</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Form (tipe|skill|unit|pelajaran)</th><th>n</th><th>Soal</th><th>Rerata</th><th>SD</th><th>α (KR-20)</th><th>SEM</th><th></th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($forms as $f)
                                <tr>
                                    <td class="py-2 font-bold"><a class="text-purple-500 hover:underline" href="{{ route('admin.la.items', ['form' => $f->form]) }}">{{ $f->form }}</a></td>
                                    <td>{{ $f->n }}@if ($f->indicative) <span class="text-xs text-yellow-600">(n kecil)</span>@endif</td>
                                    <td>{{ $f->k }}</td>
                                    <td>{{ $num($f->mean) }}</td>
                                    <td>{{ $num($f->sd) }}</td>
                                    <td class="{{ $f->alpha !== null && $f->alpha < 0.6 ? 'font-bold text-red-500' : '' }}">{{ $num($f->alpha, 3) }}</td>
                                    <td>{{ $num($f->sem) }}</td>
                                    <td class="text-xs text-slate-500">{{ $f->alpha === null ? '' : ($f->alpha >= 0.8 ? 'baik' : ($f->alpha >= 0.7 ? 'cukup' : ($f->alpha >= 0.6 ? 'rendah' : 'kurang'))) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-500">Dihitung: {{ $computed }}. α &lt; 0,6 untuk tes pendek wajar, tetapi perlu ditinjau bila dipakai untuk keputusan penting.</p>
            </div>

            <div class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-black">Butir{{ $form ? ' · ' . $form : '' }}</h2>
                    <div class="flex gap-3 text-sm">
                        <a class="font-semibold text-purple-500" href="{{ route('admin.la.items', array_filter(['form' => $form, 'flagged' => $only ? null : 1])) }}">{{ $only ? 'Tampilkan semua' : 'Hanya yang bertanda' }}</a>
                        @if ($form)<a class="font-semibold text-slate-500" href="{{ route('admin.la.items') }}">Semua form</a>@endif
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Butir</th><th>n</th><th>p</th><th>r_pb</th><th>D 27%</th><th>Kunci</th><th>Pilihan</th><th>Penanda</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($items as $i)
                                <tr>
                                    <td class="py-2 font-bold">{{ $i->item }}@if (! $form)<div class="text-[10px] font-normal text-slate-400">{{ $i->form }}</div>@endif</td>
                                    <td>{{ $i->n }}</td>
                                    <td>{{ $num($i->p) }}</td>
                                    <td class="{{ $i->r_pb !== null && $i->r_pb < 0.2 ? 'font-bold text-red-500' : '' }}">{{ $num($i->r_pb) }}</td>
                                    <td>{{ $num($i->d_index) }}</td>
                                    <td>{{ $i->key_option ?? '–' }}</td>
                                    <td class="text-xs text-slate-500">@foreach ($i->options as $o => $c){{ $o }}:{{ $c }} @endforeach</td>
                                    <td class="text-xs text-orange-600">{{ implode('; ', $i->flags) }}@if ($i->indicative) <span class="text-yellow-600">(n kecil)</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-500">Menampilkan maksimal 500 butir. Ekspor Excel memuat semuanya.</p>
            </div>

            <details class="{{ $card }}">
                <summary class="cursor-pointer text-lg font-black">Definisi dan keterbatasan</summary>
                <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p><b>p</b> = proporsi benar (terlalu mudah &gt; 0,90; terlalu sulit &lt; 0,20). <b>r_pb</b> = korelasi point-biserial antara butir dan skor sisa (total tanpa butir itu); &lt; 0,20 daya beda rendah, &lt; 0 negatif (periksa kunci). <b>D</b> = p kelompok atas 27% − p kelompok bawah 27%.</p>
                    <p><b>α</b> = k/(k−1)·(1 − Σs²ᵢ/s²_total), sama dengan KR-20 untuk butir 0/1. <b>SEM</b> = SD·√(1−α). Hanya jawaban pertama tiap siswa pada tiap form yang dihitung, dan hanya butir yang muncul pada ≥80% pengerjaan.</p>
                    <p>Tes dengan sedikit soal atau n &lt; 30 menghasilkan estimasi yang tidak stabil; tanda "n kecil" berarti indikatif. Writing dan Speaking tidak dianalisis karena tidak berskor benar/salah.</p>
                    <p>Rujukan: Crocker &amp; Algina (1986); Cronbach (1951).</p>
                </div>
            </details>
        @endif
    </div>
@endsection
