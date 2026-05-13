<?php

namespace Database\Seeders;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->make([
            'name' => 'Main Admin',
            'email' => 'admin@lesahtak.com',
            'password' => 'password123',
        ]);

        // toArray() omits $hidden attributes, so password was missing from updateOrCreate().
        User::query()->updateOrCreate(
            ['email' => 'admin@lesahtak.com'],
            $admin->makeVisible(['password', 'remember_token'])->toArray()
        );

        $this->call(DoctorCatalogSeeder::class);
        $this->call(AiKnowledgeSeeder::class);
        $this->call(SymptomAdvisorRulesSeeder::class);

        if (! AdminSetting::query()->exists()) {
            AdminSetting::query()->create([
                'site_name' => 'Lesahtak',
                'site_email' => 'admin@lesahtak.com',
                'site_phone' => '01012345678',
                'address' => 'Beni Suef, Egypt',
                'email_notifications' => true,
                'sms_notifications' => false,
                'new_appointment_alert' => true,
                'payment_alert' => true,
                'language' => 'ar',
                'max_appointments_per_day' => 20,
                'appointment_duration' => 30,
            ]);
        }

        User::factory()->patient()->count(8)->create();
    }
}
