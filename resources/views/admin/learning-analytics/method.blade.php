@extends('layouts.admin')

@section('content')
    @php $card = 'rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]'; @endphp
    <div class="space-y-6">
        @include('admin.learning-analytics._tabs')
        <div>
            <h1 class="text-3xl font-black">Metodologi &amp; Keterbatasan</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan rumus, ambang, dan batas klaim setiap metrik agar dapat dilaporkan secara jujur dalam penelitian.</p>
        </div>

        @foreach ([
            ['Keterlibatan', 'Aktivitas diturunkan dari learning_events, pengumpulan asesmen, kemajuan pelajaran, dan transaksi gamifikasi karena tidak ada log login/halaman. Sesi = rangkaian peristiwa dengan jeda ≤ 30 menit; durasi sesi adalah estimasi. Indeks keteraturan 0–100 dari sebaran hari aktif 28 hari; pola: konsisten, sporadis, menumpuk (≥ 50% menit aktif dalam sehari), menurun, berhenti (≥ 14 hari tidak aktif), tidak aktif.'],
            ['Hasil belajar', 'Gain = post − pre; gain ternormalisasi Hake = (post − pre)/(100 − pre); uji-t berpasangan, Wilcoxon (eksak bila n ≤ 25), ukuran efek Hedges g dengan CI95. Tanpa kelompok kontrol, perubahan tidak dapat diklaim sebagai efek pembelajaran.'],
            ['Kualitas soal', 'Teori tes klasik: tingkat kesulitan p, korelasi biserial-titik terkoreksi (rest-score), indeks daya beda D (27% atas–bawah), α Cronbach/KR-20, SEM. Penanda ditampilkan hanya bila jumlah jawaban memadai.'],
            ['Penguasaan (BKT)', 'Bayesian Knowledge Tracing per kompetensi (p_init, p_learn, p_slip, p_guess) dicari lewat grid dengan batas p_slip, p_guess ≤ 0,3. Penguasaan ≥ 0,95. "Berputar-putar" bila banyak percobaan tanpa kenaikan penguasaan. Parameter butuh data cukup; kompetensi dengan data sedikit tidak ditampilkan.'],
            ['SRL & klaster', 'Fitur perilaku (hint, waktu jawab, usaha menghadapi salah) distandarkan lalu k-means (k dipilih dengan siluet). Profil adalah deskripsi perilaku, bukan sifat siswa; dapat berubah antar periode.'],
            ['Peringatan dini', 'Skor aturan 0–100 dari komponen berbobot yang dapat diubah admin; dinormalkan hanya pada komponen yang punya data dan diberi tingkat keyakinan. Model regresi logistik (ridge) memprediksi posttest < 70, divalidasi silang 5-lipat, dan hanya ditampilkan bila n ≥ 60, tiap kelas ≥ 15, AUC ≥ 0,70. Diperiksa per sekolah untuk keadilan. Skor adalah alat bantu keputusan guru, bukan keputusan otomatis.'],
            ['Evaluasi intervensi', 'Perubahan hari aktif 14 hari sebelum vs sesudah tindak lanjut, dibandingkan dengan siswa berisiko yang tidak ditindaklanjuti (Welch). Bukan acak: ada bias seleksi (guru memilih siswa) sehingga hasilnya asosiasi.'],
            ['Privasi', 'Ekspor riset memakai ID pseudonim HMAC-SHA256 dan kode sekolah; hanya siswa yang setuju. Ekspor teridentifikasi atau tanpa persetujuan memerlukan alasan dan dicatat. Guru hanya melihat kelas yang diampu; siswa hanya melihat data sendiri.'],
        ] as [$t, $d])
            <div class="{{ $card }}"><h2 class="text-lg font-black">{{ $t }}</h2><p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $d }}</p></div>
        @endforeach
    </div>
@endsection
