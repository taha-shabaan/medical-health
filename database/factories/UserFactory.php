<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('01#########'),
            'gender' => fake()->randomElement(['male', 'female']),
            'height' => fake()->numberBetween(155, 185),
            'blood_type' => fake()->randomElement(['A+', 'B+', 'O+']),
            'weight' => (string) fake()->numberBetween(55, 95),
            'governorate' => 'بني سويف',
            'area' => 'مركز بني سويف',
            'address' => null,
            'date_of_birth' => fake()->date(),
            'avatar' => null,
            'role' => 'patient',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'patient',
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'doctor',
            'specialty' => fake()->randomElement([
                'باطنة', 'قلب وأوعية دموية', 'طب الأسنان', 'طب الأطفال', 'مخ وأعصاب',
            ]),
            'governorate' => 'بني سويف',
            'area' => fake()->randomElement(['مركز الواسطى', 'مركز ببا', 'مركز سمسطا', 'بني سويف']),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
