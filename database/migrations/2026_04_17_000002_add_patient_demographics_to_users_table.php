<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('blood_type', 8)->nullable()->after('height');
            $table->string('weight', 12)->nullable()->after('blood_type');
            $table->string('governorate', 120)->nullable()->after('weight');
            $table->string('area', 120)->nullable()->after('governorate');
            $table->text('address')->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blood_type', 'weight', 'governorate', 'area', 'address']);
        });
    }
};
