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
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            // The model stores the token ENCRYPTED (cast `encrypted`) — the
            // column must hold the ~244-char payload, hence text. Lookups go
            // through token_hash (sha-256 hex, fixed 64 chars).
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->timestamps();

            $table->unique('workflow_id');
        });

        Schema::create('webhook_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64);
            $table->string('request_id_hash', 64);
            $table->timestamp('received_at');

            $table->unique(['token_hash', 'request_id_hash']);
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_requests');
        Schema::dropIfExists('webhook_endpoints');
    }
};
