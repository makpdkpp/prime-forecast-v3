<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationHardeningTest extends TestCase
{
    public function test_current_password_verification_accepts_bcrypt_and_rejects_wrong_password(): void
    {
        $user = new User();
        $user->password = Hash::make('correct horse battery staple');

        $this->assertTrue($user->verifyCurrentPassword('correct horse battery staple'));
        $this->assertFalse($user->verifyCurrentPassword('wrong password'));
    }

    public function test_password_reset_rejects_passwords_shorter_than_twelve_characters(): void
    {
        $response = $this->post('/reset-password/invalid-token', [
            'email' => 'user@example.com',
            'password' => 'short123',
            'password_confirmation' => 'short123',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
