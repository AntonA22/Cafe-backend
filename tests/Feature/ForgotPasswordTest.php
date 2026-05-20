<?php

namespace Tests\Feature;

use App\Mail\ForgotPasswordTemporaryPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_not_found_without_sending_mail_for_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Почта не зарегистрирована');

        Mail::assertNothingSent();
    }

    public function test_forgot_password_sends_temporary_password_only_to_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'registered@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'REGISTERED@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If an account with this email exists, a temporary password has been sent.');

        Mail::assertSent(
            ForgotPasswordTemporaryPasswordMail::class,
            fn (ForgotPasswordTemporaryPasswordMail $mail) => $mail->hasTo($user->email)
                && $mail->envelope()->from->name === 'Зарядка кофе'
        );

        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }
}
