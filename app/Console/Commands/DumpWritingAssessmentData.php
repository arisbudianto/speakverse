<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\WritingQuestion;
use Illuminate\Console\Command;

class DumpWritingAssessmentData extends Command
{
    protected $signature = 'qa:dump-writing-assessment-data';
    protected $description = 'Dump lesson_id dan isi WritingQuestion untuk pretest & posttest (diagnosa restrukturisasi tier)';

    public function handle(): int
    {
        $lessons = Lesson::query()
            ->where('skill_type', 'writing')
            ->get();

        foreach ($lessons as $lesson) {
            $unit = $lesson->unit;

            $this->line('---');
            $this->line("lesson_id={$lesson->id} unit_id={$lesson->unit_id} unit_type=" . ($unit->type ?? 'null') . " unit_title=" . ($unit->title ?? '-'));

            $questions = WritingQuestion::query()
                ->where('lesson_id', $lesson->id)
                ->whereNull('writing_material_id')
                ->get();

            if ($questions->isEmpty()) {
                $this->line('  (no pretest/posttest-style questions here)');
                continue;
            }

            foreach ($questions as $q) {
                $this->line("  question_id={$q->id}");
                $this->line('  question=' . str_replace("\n", ' | ', $q->question));
                $this->line('  sample_answer=' . str_replace("\n", ' | ', (string) $q->sample_answer));
            }
        }

        return self::SUCCESS;
    }
}
