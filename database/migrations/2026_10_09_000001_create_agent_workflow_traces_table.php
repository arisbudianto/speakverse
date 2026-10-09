<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_workflow_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('workflow_id')->unique();
            $table->string('status', 20);
            $table->json('steps');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_workflow_traces');
    }
};
