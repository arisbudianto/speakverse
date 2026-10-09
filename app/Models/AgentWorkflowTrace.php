<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentWorkflowTrace extends Model
{
    protected $fillable = ['workflow_id', 'status', 'steps', 'idempotency_key', 'input_fingerprint', 'result_payload'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'result_payload' => 'array'];
    }
}
