<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Learning Analytics — pengaturan bawaan
    |--------------------------------------------------------------------------
    | Bobot risiko dapat ditimpa admin lewat halaman Peringatan Dini (tabel la_settings).
    | Skor risiko = Σ(bobot × komponen[0..1]) / Σ(bobot komponen yang punya data) × 100.
    */
    'risk' => [
        'weights' => [
            'inactivity' => 20,   // hari sejak aktif terakhir (14 hari = penuh)
            'irregular' => 10,    // 1 − indeks keteraturan/100
            'decline' => 10,      // kemiringan hari-aktif/minggu negatif (−2 = penuh)
            'hint_dependence' => 10, // porsi butir dengan hint level 3 (0,30 = penuh)
            'inferential' => 15,  // salah inferensial percobaan pertama relatif terhadap ambang intervensi
            'lag' => 15,          // tertinggal dari median progres kelas (0,5 = penuh)
            'low_accuracy' => 12, // 1 − akurasi percobaan pertama
            'wheel' => 8,         // jumlah kompetensi berputar-putar (2 = penuh)
        ],
        'level_high' => 60,
        'level_medium' => 35,
        'posttest_threshold' => 70,   // sasaran model statistik: posttest di bawah ini
        'min_students_model' => 60,
        'min_class_model' => 15,
        'min_auc_to_show' => 0.70,
    ],
    'engagement' => [
        'stop_days' => 14,
    ],
];
