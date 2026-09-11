<?php

namespace App\Http\Controllers;

use App\Models\VocabularyPretest;
use Illuminate\Http\Request;

class AdminVocabularyPretestController extends Controller
{     public function index()
    {
        $pretests = VocabularyPretest::all();

        return view(
            'admin.missions.vocabulary-pretests.index',
            compact('pretests')
        );
    }

    public function create()
    {
        return view(
            'admin.missions.vocabulary-pretests.create'
        );
    }

    public function store(Request $request)
    {
        VocabularyPretest::create([
            'category' => $request->category,
            'question' => $request->question,
            'option_a' => $request->option_a,
            'option_b' => $request->option_b,
            'option_c' => $request->option_c,
            'option_d' => $request->option_d,
            'option_e' => $request->option_e,
            'correct_answer' => $request->correct_answer,
        ]);
        return back()->with(
            'success',
            'Question added successfully'
        );

    }
}