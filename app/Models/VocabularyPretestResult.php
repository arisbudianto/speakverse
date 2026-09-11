<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabularyPretestResult extends Model
{
    protected $fillable = [
        'user_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    /**
     * Pengguna yang mengerjakan Vocabulary Pretest.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}