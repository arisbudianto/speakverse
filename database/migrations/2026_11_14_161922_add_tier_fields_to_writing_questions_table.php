<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Restrukturisasi tingkat kesulitan Pretest/Posttest Writing
    |--------------------------------------------------------------------------
    |
    | Masukan Anggertha (PTIK 23): sebelum siswa langsung diminta membuat
    | outline + mengembangkan teks (skill lanjutan), beri dulu soal
    | bertingkat dari mudah ke sedang: merangkai kata jadi kalimat,
    | kalimat jadi paragraf, lalu identifikasi jenis teks - baru masuk ke
    | tugas outline+develop yang sudah ada (tetap tidak diubah).
    |
    | 'tier' HANYA label tampilan/urutan pengerjaan. Ia TIDAK mengubah
    | bobot final_score - StudentAssessmentController tetap merata-ratakan
    | semua WritingQuestion di lesson yang sama seperti sebelumnya, apa
    | pun tier-nya. Default 'advanced' + grading_type 'ai_rubric' supaya
    | 4 WritingQuestion yang sudah ada (pretest id 9,10 & posttest id
    | 18,19) tidak berubah perilakunya sama sekali.
    */
    public function up(): void
    {
        Schema::table('writing_questions', function (Blueprint $table) {
            $table->enum('tier', ['easy', 'medium', 'advanced'])
                ->default('advanced')
                ->after('writing_material_id');

            $table->enum('grading_type', ['ai_rubric', 'exact_match', 'multiple_choice'])
                ->default('ai_rubric')
                ->after('tier');

            /*
             * exact_match: jawaban kanonik untuk dibandingkan (dinormalisasi
             * saat dibandingkan: lowercase, trim, spasi dirapikan).
             *
             * multiple_choice: indeks pilihan benar (string angka, misal "1"),
             * dicocokkan dengan key array di kolom 'options'.
             */
            $table->text('correct_answer')
                ->nullable()
                ->after('sample_answer');

            /*
             * Daftar pilihan untuk multiple_choice, mis.:
             * ["Narrative", "Descriptive", "Expository", "Persuasive"]
             */
            $table->json('options')
                ->nullable()
                ->after('correct_answer');
        });
    }

    public function down(): void
    {
        Schema::table('writing_questions', function (Blueprint $table) {
            $table->dropColumn(['tier', 'grading_type', 'correct_answer', 'options']);
        });
    }
};
