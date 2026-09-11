<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class VocabularyPretest extends Model
{
    protected $fillable = [
        'category',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'option_e',
        'correct_answer'
    ];

}
