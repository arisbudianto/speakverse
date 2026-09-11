<?php

namespace App\Http\Controllers;

use App\Models\VocabularyPretest;
use App\Models\VocabularyPretestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VocabularyPretestController extends Controller
{
    public function index()
    {
        $questions = VocabularyPretest::where(
            'category',
            'vocabulary'
        )
        ->inRandomOrder()
        ->take(20)
        ->get();

        return view(
            'vocabulary.pretest',
            compact('questions')
        );
    }

    public function submit(Request $request)
    {
        $score = 0;

        foreach ($request->answers as $id => $answer) {

            $question = VocabularyPretest::find($id);

            if ($question->correct_answer == $answer) {
                $score += 5;
            }
        }

        VocabularyPretestResult::create([
            'user_id' => Auth::id(),
            'score' => $score
        ]);

        return back()->with(
            'success',
            'Your score : '.$score
        );
    }
}