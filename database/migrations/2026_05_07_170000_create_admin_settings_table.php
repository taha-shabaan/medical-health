<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 255)->default('Lesahtak');
            $table->string('site_email', 255)->default('admin@lesahtak.com');
            $table->string('site_phone', 30)->default('01012345678');
            $table->string('address', 255)->default('Beni Suef, Egypt');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('new_appointment_alert')->default(true);
            $table->boolean('payment_alert')->default(true);
            $table->string('language', 8)->default('ar');
            $table->unsignedSmallInteger('max_appointments_per_day')->default(20);
            $table->unsignedSmallInteger('appointment_duration')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
