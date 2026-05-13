<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Conversations table
         */
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ⚡ performance
            $table->index('user_id');
        });

        /**
         * Messages table
         */
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();

            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');

            // 🔥 RAG fields
            $table->json('citations')->nullable();
            $table->json('retrieved_chunks')->nullable();

            // 🔥 AI tracking (important)
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->boolean('disclaimer_shown')->default(false);

            $table->timestamps();

            // ⚡ performance
            $table->index('conversation_id');
            $table->index('role');
        });

        /**
         * Audit Logs (append-only)
         */
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();

            $table->string('user_ip', 45);
            $table->string('query_hash', 64);

            // Provider-neutral retrieval telemetry
            $table->json('query_vector')->nullable();
            $table->json('retrieved_source_ids');

            // 🔥 LLM tracking
            $table->string('llm_model');
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->unsignedInteger('source_count')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('model_response_status')->default('success');

            $table->boolean('safety_triggered')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->index('message_id');
        });

        /**
         * Document Sources
         */
        Schema::create('document_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            $table->enum('type', ['pdf', 'guideline', 'database', 'drug_monograph']);

            $table->string('version')->nullable();
            $table->date('published_at')->nullable();

            // 🔥 vector DB reference (better than hardcoding pinecone only)
            $table->string('vector_db')->default('pinecone');
            $table->string('index_name');
            $table->string('namespace');

            $table->string('file_hash', 64)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sources');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
