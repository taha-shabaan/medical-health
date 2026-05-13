<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DoctorCatalogSeeder extends Seeder
{
    /**
     * Seeds doctors from database/data/doctors_catalog.json (name, specialty, area/center, address, avatar).
     * Emails are stable per row for idempotent re-seeding.
     */
    public function run(): void
    {
        $path = database_path('data/doctors_catalog.json');
        if (! File::exists($path)) {
            $this->command?->warn('Skipping DoctorCatalogSeeder: doctors_catalog.json not found at '.$path);

            return;
        }

        $rows = json_decode(File::get($path), true);
        if (! is_array($rows)) {
            $this->command?->error('Invalid JSON in doctors_catalog.json');

            return;
        }

        $governorate = 'بني سويف';

        foreach ($rows as $index => $row) {
            $name = $row['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $email = 'doctor_catalog_'.$index.'@seed.lesahtak.local';

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password123',
                    'role' => 'doctor',
                    'is_active' => true,
                    'specialty' => $row['specialty'] ?? null,
                    'governorate' => $governorate,
                    'area' => $row['area'] ?? null,
                    'address' => $row['address'] ?? null,
                    'avatar' => $row['avatar'] ?? null,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command?->info('Doctor catalog seeded: '.count($rows).' rows processed.');
    }
}
