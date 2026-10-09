<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_workflow_traces', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('input_fingerprint', 64)->nullable();
            $table->json('result_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_workflow_traces', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'input_fingerprint', 'result_payload']);
        });
    }
};
