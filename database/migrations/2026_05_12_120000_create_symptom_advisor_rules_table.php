<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_advisor_rules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            /** specialty = map symptoms to DB specialty LIKE terms; emergency = safety redirect */
            $table->enum('type', ['specialty', 'emergency']);
            /**
             * any_keyword: any substring match in keywords[] triggers message_ar (emergency) or scoring (specialty).
             * compound_chest: requires (ألم + صدر) and (شديد or حاد) in normalized text.
             */
            $table->string('match_mode', 32)->default('any_keyword');
            $table->string('label_ar', 191)->nullable();
            $table->text('message_ar')->nullable();
            /** @var list<string>|null */
            $table->json('keywords')->nullable();
            /** @var list<string>|null — LIKE fragments on users.specialty (specialty rules only) */
            $table->json('db_specialty_terms')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_advisor_rules');
    }
};
