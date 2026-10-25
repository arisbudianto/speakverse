@extends('layouts.admin')

@section('content')
    @php
        $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $input = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-900';
    @endphp

    <div class="space-y-8">
        @include('admin.learning-analytics._tabs')
        <div>
            <h1 class="text-3xl font-black">Analitik Lanjutan · Ekspor Riset</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Data dipseudonimkan (ID acak, tanpa nama/email) dan hanya siswa yang menyetujui. Setiap ekspor dan perubahan kelompok tercatat.</p>
        </div>

        @if (session('status'))<div class="rounded-2xl bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('status') }}</div>@endif

        @if (! $ready)
            <div class="{{ $card }}"><p class="font-bold">Tabel persetujuan belum ada.</p><p class="mt-2 text-sm text-slate-500">Jalankan <code>php artisan migrate</code>.</p></div>
        @else
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="{{ $card }}"><p class="text-sm font-semibold text-slate-500">Siswa</p><p class="mt-2 text-4xl font-black">{{ $total }}</p></div>
                <div class="{{ $card }}"><p class="text-sm font-semibold text-slate-500">Setuju</p><p class="mt-2 text-4xl font-black text-green-600">{{ $yes }}</p></div>
                <div class="{{ $card }}"><p class="text-sm font-semibold text-slate-500">Menolak</p><p class="mt-2 text-4xl font-black text-orange-500">{{ $no }}</p></div>
                <div class="{{ $card }}"><p class="text-sm font-semibold text-slate-500">Belum menjawab</p><p class="mt-2 text-4xl font-black">{{ max(0, $total - $yes - $no) }}</p></div>
            </div>

            <form method="POST" action="{{ route('admin.la.research.export') }}" class="{{ $card }}">
                @csrf
                <h2 class="text-lg font-black">Unduh dataset (.xlsx)</h2>
                <p class="mt-1 text-sm text-slate-500">Bawaan: pseudonim, hanya siswa yang setuju. Opsi di bawah memerlukan alasan dan dicatat di log akses.</p>
                <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="identified" value="1"> Sertakan identitas (nama, email)</label>
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="include_unconsented" value="1"> Sertakan siswa yang belum/tidak menyetujui</label>
                <label class="mt-3 block text-sm font-semibold">Alasan (wajib untuk opsi di atas)<br><input name="reason" value="{{ old('reason') }}" maxlength="300" class="{{ $input }} w-full max-w-xl"></label>
                @error('reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <button class="mt-4 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-white dark:text-slate-900">Unduh</button>
            </form>

            <form method="POST" action="{{ route('admin.la.research.group') }}" class="{{ $card }}">
                @csrf
                <h2 class="text-lg font-black">Kelompok penelitian</h2>
                <p class="mt-1 text-sm text-slate-500">Tetapkan kontrol/perlakuan per kelas untuk desain kuasi-eksperimen. Saat ini: @forelse ($groups as $g => $c)<b>{{ $g }}</b> {{ $c }}@if (! $loop->last), @endif @empty belum ada @endforelse.</p>
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <label class="text-sm font-semibold">Sekolah<br><select name="school" class="{{ $input }}">@foreach ($schools as $s)<option>{{ $s }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold">Tingkat<br><select name="grade" class="{{ $input }}"><option value="">Semua</option><option>X</option><option>XI</option><option>XII</option></select></label>
                    <label class="text-sm font-semibold">Paralel<br><select name="parallel" class="{{ $input }}"><option value="">Semua</option><option>A</option><option>B</option><option>C</option><option>D</option></select></label>
                    <label class="text-sm font-semibold">Kelompok<br><select name="group" class="{{ $input }}"><option value="perlakuan">Perlakuan</option><option value="kontrol">Kontrol</option><option value="">(kosongkan)</option></select></label>
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-white dark:text-slate-900">Terapkan</button>
                </div>
            </form>

            <div class="{{ $card }}">
                <h2 class="text-lg font-black">Log akses terbaru</h2>
                <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Waktu</th><th>Pengguna</th><th>Aksi</th><th>Target</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($log as $l)<tr><td class="py-2">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('d/m/Y H:i') }}</td><td>{{ $l->name ?? '–' }}</td><td>{{ $l->action }}</td><td>{{ $l->target }}</td></tr>
                    @empty<tr><td colspan="4" class="py-4 text-slate-500">Belum ada catatan.</td></tr>@endforelse
                    </tbody></table></div>
            </div>
        @endif
    </div>
@endsection
