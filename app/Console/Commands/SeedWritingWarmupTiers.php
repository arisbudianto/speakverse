<?php

namespace App\Console\Commands;

use App\Models\WritingQuestion;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| Seed sementara: soal warm-up (easy) + checkpoint (medium) untuk
| pretest (lesson 19) dan posttest (lesson 23) writing.
|--------------------------------------------------------------------------
|
| Dibuat untuk dijalankan SEKALI di production, lalu dihapus (pola yang
| sama dengan command dump data sebelumnya). Idempoten: jika pertanyaan
| dengan 'question' yang sama sudah ada di lesson tsb, baris itu dilewati
| supaya aman dijalankan berulang kali tanpa duplikasi.
*/
class SeedWritingWarmupTiers extends Command
{
    protected $signature = 'qa:seed-writing-warmup-tiers {--dry-run}';

    protected $description = 'Seed soal easy/medium (warm-up) untuk pretest (lesson 19) dan posttest (lesson 23) writing';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = [
            // ----- PRETEST (lesson_id = 19) -----
            [
                'lesson_id' => 19,
                'tier' => 'easy',
                'grading_type' => 'exact_match',
                'question' => 'Arrange these words into one correct English sentence: "ago / a / lived / king / long / time / there"',
                'correct_answer' => 'there lived a king a long time ago',
                'sample_answer' => 'There lived a king a long time ago.',
            ],
            [
                'lesson_id' => 19,
                'tier' => 'easy',
                'grading_type' => 'exact_match',
                'question' => 'Put these three sentences in the correct order to form a short paragraph: (1) "Finally, he became the wisest king in the land." (2) "A long time ago, there lived a young king." (3) "Every day, he asked his teacher many questions." Write them as one paragraph, in the correct order.',
                'correct_answer' => 'a long time ago there lived a young king every day he asked his teacher many questions finally he became the wisest king in the land',
                'sample_answer' => 'A long time ago, there lived a young king. Every day, he asked his teacher many questions. Finally, he became the wisest king in the land.',
            ],
            [
                'lesson_id' => 19,
                'tier' => 'medium',
                'grading_type' => 'multiple_choice',
                'question' => 'Read this paragraph: "A long time ago, there lived a young king who wanted to become wise. Every day, he asked his teacher many questions. One day, his teacher gave him a magic book, and from that day, the king grew wiser and wiser." What type of text is this paragraph?',
                'correct_answer' => 'Narrative',
                'options' => ['Narrative', 'Descriptive', 'Expository', 'Persuasive'],
                'sample_answer' => 'Narrative',
            ],

            // ----- POSTTEST (lesson_id = 23) -----
            [
                'lesson_id' => 23,
                'tier' => 'easy',
                'grading_type' => 'exact_match',
                'question' => 'Arrange these words into one correct English sentence: "homework / every / I / do / my / afternoon"',
                'correct_answer' => 'i do my homework every afternoon',
                'sample_answer' => 'I do my homework every afternoon.',
            ],
            [
                'lesson_id' => 23,
                'tier' => 'easy',
                'grading_type' => 'exact_match',
                'question' => 'Put these three sentences in the correct order to form a short paragraph: (1) "After that, I do my homework and review my notes." (2) "Every afternoon, I follow the same routine after school." (3) "Finally, I help my mother prepare dinner before it gets dark." Write them as one paragraph, in the correct order.',
                'correct_answer' => 'every afternoon i follow the same routine after school after that i do my homework and review my notes finally i help my mother prepare dinner before it gets dark',
                'sample_answer' => 'Every afternoon, I follow the same routine after school. After that, I do my homework and review my notes. Finally, I help my mother prepare dinner before it gets dark.',
            ],
            [
                'lesson_id' => 23,
                'tier' => 'medium',
                'grading_type' => 'multiple_choice',
                'question' => 'Read this paragraph: "Every afternoon, I follow the same routine after school. First, I do my homework and review my notes. Then, I help my mother prepare dinner before it gets dark." What type of text is this paragraph?',
                'correct_answer' => 'Narrative',
                'options' => ['Narrative', 'Descriptive', 'Expository', 'Persuasive'],
                'sample_answer' => 'Narrative',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = WritingQuestion::query()
                ->where('lesson_id', $row['lesson_id'])
                ->where('question', $row['question'])
                ->exists();

            if ($exists) {
                $skipped++;
                $this->line('SKIP (sudah ada): lesson '.$row['lesson_id'].' - '.mb_substr($row['question'], 0, 50).'...');
                continue;
            }

            $this->line('CREATE: lesson '.$row['lesson_id'].' tier='.$row['tier'].' type='.$row['grading_type']);

            if (!$dryRun) {
                WritingQuestion::create($row);
            }

            $created++;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Selesai. Dibuat: {$created}, dilewati (sudah ada): {$skipped}.");

        return self::SUCCESS;
    }
}
