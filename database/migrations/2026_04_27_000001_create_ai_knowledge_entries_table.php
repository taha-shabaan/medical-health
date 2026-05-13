<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_entries', function (Blueprint $table) {
            $table->id();
            /** Comma-separated triggers; message matches if any trigger appears in user text (substring). */
            $table->text('triggers');
            /** Full reply text shown to the user. */
            $table->text('response');
            /** null = all contexts; else patient|doctor|general */
            $table->string('role_context', 32)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'role_context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_entries');
    }
};
