<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\WritingQuestion;
use Illuminate\Console\Command;

class DumpWritingAssessmentData extends Command
{
    protected $signature = 'qa:dump-writing-assessment-data {--full= : question_id untuk tampilkan teks lengkap}';
    protected $description = 'Dump ringkas lesson_id/question_id pretest & posttest writing (diagnosa restrukturisasi tier)';

    public function handle(): int
    {
        if ($fullId = $this->option('full')) {
            $q = WritingQuestion::find((int) $fullId);

            if (!$q) {
                $this->line("question_id {$fullId} not found");
                return self::SUCCESS;
            }

            $this->line("ID={$q->id} LESSON={$q->lesson_id}");
            $this->line('Q: ' . $q->question);
            $this->line('SAMPLE: ' . (string) $q->sample_answer);

            return self::SUCCESS;
        }

        $lessons = Lesson::query()
            ->where('skill_type', 'writing')
            ->get();

        foreach ($lessons as $lesson) {
            $unit = $lesson->unit;

            $questions = WritingQuestion::query()
                ->where('lesson_id', $lesson->id)
                ->whereNull('writing_material_id')
                ->get();

            $ids = $questions->pluck('id')->implode(',');

            $this->line(
                "L{$lesson->id} U{$lesson->unit_id} type=" . ($unit->type ?? '-')
                . ' title=' . ($unit->title ?? '-')
                . " qcount={$questions->count()} qids=[{$ids}]"
            );
        }

        return self::SUCCESS;
    }
}
