<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['doctor_id', 'appointment_date'], 'appointments_doctor_date_idx');
            $table->index(['doctor_id', 'appointment_date', 'status'], 'appointments_doctor_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_doctor_date_idx');
            $table->dropIndex('appointments_doctor_date_status_idx');
        });
    }
};
