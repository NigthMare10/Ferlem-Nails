<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Customer
    {
        return Customer::create(['name' => 'Clienta de prueba', 'email' => 'recovery@example.test', 'password' => 'OriginalPassword123']);
    }

    public function test_email_alone_never_changes_password_and_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $c = $this->member();
        $original = $c->password;
        $this->post('/clientes/olvide-contrasena', ['email' => $c->email, 'password' => 'AttackerPassword123', 'password_confirmation' => 'AttackerPassword123'])->assertSessionHas('success');
        $message = session('success');
        $this->assertSame($original, $c->fresh()->password);
        Notification::assertSentTo($c, CustomerResetPassword::class);
        $this->post('/clientes/olvide-contrasena', ['email' => 'unknown@example.test'])->assertSessionHas('success', $message);
        $this->post('/clientes/restablecer-contrasena', ['email' => $c->email, 'password' => 'AttackerPassword123', 'password_confirmation' => 'AttackerPassword123'])->assertSessionHasErrors('token');
        $this->assertSame($original, $c->fresh()->password);
    }

    public function test_token_is_single_use_and_cannot_be_replayed(): void
    {
        $c = $this->member();
        $token = Password::broker('customers')->createToken($c);
        $data = ['email' => $c->email, 'token' => $token, 'password' => 'UpdatedPassword123', 'password_confirmation' => 'UpdatedPassword123'];
        $this->post('/clientes/restablecer-contrasena', $data)->assertRedirect('/clientes/login');
        $password = $c->fresh()->password;
        $this->post('/clientes/restablecer-contrasena', [...$data, 'password' => 'ReplayPassword123', 'password_confirmation' => 'ReplayPassword123'])->assertSessionHasErrors('email');
        $this->assertSame($password, $c->fresh()->password);
    }

    public function test_expired_and_foreign_tokens_are_rejected(): void
    {
        $c = $this->member();
        $token = Password::broker('customers')->createToken($c);
        $original = $c->password;
        $data = ['email' => $c->email, 'token' => $token, 'password' => 'UpdatedPassword123', 'password_confirmation' => 'UpdatedPassword123'];
        $this->post('/clientes/restablecer-contrasena', [...$data, 'email' => 'different@example.test'])->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post('/clientes/restablecer-contrasena', $data)->assertSessionHasErrors('email');
        $this->assertSame($original, $c->fresh()->password);
    }
}
