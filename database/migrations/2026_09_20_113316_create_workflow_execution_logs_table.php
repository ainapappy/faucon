<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
            // One row per node per attempt — the unique index is the
            // writer's upsert guard. node_key is NULL for event rows:
            // NULLs are distinct in unique indexes, so event rows never
            // collide.
            $table->unsignedInteger('attempt')->default(1);
            $table->string('kind', 10);
            // 64 chars, mirroring workflow_nodes.key: keeps the
            // (execution, attempt, node_key) unique index within the
            // MariaDB key-length budget on utf8mb4.
            $table->string('node_key', 64)->nullable();
            $table->string('node_type')->nullable();
            $table->string('node_name')->nullable();
            $table->string('status', 10)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('message')->nullable();
            $table->string('level', 10)->default('info');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('error')->nullable();
            // Milliseconds elapsed since the START of the attempt (never
            // since row creation — the queue wait must not appear in t).
            $table->unsignedInteger('offset_ms')->default(0);
            $table->timestamps();

            $table->index(['workflow_execution_id', 'attempt']);
            // Explicit short name: MariaDB caps identifiers at 64 chars
            // and the conventional name exceeds it.
            $table->unique(['workflow_execution_id', 'attempt', 'node_key'], 'workflow_execution_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_logs');
    }
};
