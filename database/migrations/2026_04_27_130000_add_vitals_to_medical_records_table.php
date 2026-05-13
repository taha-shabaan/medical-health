<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->string('blood_pressure', 32)->nullable()->after('patient_id');
            $table->string('blood_sugar', 32)->nullable()->after('blood_pressure');
            $table->string('body_weight', 32)->nullable()->after('blood_sugar');
            $table->string('body_temperature', 32)->nullable()->after('body_weight');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['blood_pressure', 'blood_sugar', 'body_weight', 'body_temperature']);
        });
    }
};
