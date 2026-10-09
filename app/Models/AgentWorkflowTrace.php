<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentWorkflowTrace extends Model
{
    protected $fillable = ['workflow_id', 'status', 'steps'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }
}
