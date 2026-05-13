<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->string('identifier')->nullable()->after('email')->index();
            $table->string('channel', 16)->default('email')->after('identifier')->index();
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->dropIndex(['identifier']);
            $table->dropIndex(['channel']);
            $table->dropColumn(['identifier', 'channel']);
        });
    }
};
