<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        $email = 'new-patient-'.uniqid().'@example.com';
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Patient',
            'email' => $email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'gender' => 'male',
            'height' => 175,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user', 'message'])
            ->assertJsonPath('user.email', $email);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'locked@example.com',
            'password' => 'password12',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password12',
        ])->assertStatus(403);
    }

    public function test_patient_can_login_with_phone_number(): void
    {
        $user = User::factory()->patient()->create([
            'phone' => '01012345678',
            'password' => 'password12',
        ]);

        $this->postJson('/api/auth/login', [
            'identifier' => $user->phone,
            'password' => 'password12',
            'role' => 'patient',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user', 'message'])
            ->assertJsonPath('user.id', $user->id);
    }
}
