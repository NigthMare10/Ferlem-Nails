<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NgrokPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_forwarded_https_renders_compiled_assets_and_preserves_login_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('secure-password'),
        ]);
        $user->assignRole('employee');

        TrustProxies::at('127.0.0.1');
        config(['session.secure' => true]);
        $headers = [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'studio-preview.ngrok-free.app',
            'X-Forwarded-Port' => '443',
        ];
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => '8000',
        ];

        Route::middleware('web')->get('/_ngrok-preview-probe', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'login_url' => url('/login'),
            'login_route' => route('login'),
            'asset_url' => asset('build/probe.css'),
        ]));

        $this->withServerVariables($server)->withHeaders($headers)->get('/_ngrok-preview-probe')
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'login_url' => 'https://studio-preview.ngrok-free.app/login',
                'login_route' => 'https://studio-preview.ngrok-free.app/login',
                'asset_url' => 'https://studio-preview.ngrok-free.app/build/probe.css',
            ]);

        $this->withServerVariables($server)->withHeaders($headers)->get('/')
            ->assertRedirect('https://studio-preview.ngrok-free.app/login');

        $loginPage = $this->withServerVariables($server)->withHeaders($headers)->get('/login');
        $loginPage->assertOk()
            ->assertSee('https://studio-preview.ngrok-free.app/build/assets/', false)
            ->assertDontSee('http://studio-preview.ngrok-free.app/build/', false)
            ->assertDontSee('127.0.0.1:5173', false)
            ->assertDontSee('@vite/client', false)
            ->assertDontSee('resources/js/app.ts', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth', null));

        $response = $this->withServerVariables($server)->withHeaders($headers)->post('/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ]);

        $this->assertStringStartsWith('https://studio-preview.ngrok-free.app/', $response->headers->get('Location'));
        $sessionCookie = collect($response->headers->getCookies())->first(
            fn ($cookie) => $cookie->getName() === config('session.cookie'),
        );
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertAuthenticatedAs($user);

        $this->withServerVariables($server)->withHeaders($headers)->get('/sales/new')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_direct_http_and_untrusted_forwarded_headers_do_not_force_https(): void
    {
        TrustProxies::flushState();
        config(['session.secure' => false]);
        $server = [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => '8000',
        ];

        Route::middleware('web')->get('/_local-http-probe', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'login_url' => url('/login'),
            'asset_url' => asset('build/probe.css'),
        ]));

        $this->withServerVariables($server)->get('/_local-http-probe')
            ->assertJson([
                'secure' => false,
                'login_url' => 'http://localhost/login',
                'asset_url' => 'http://localhost/build/probe.css',
            ]);

        $this->withServerVariables($server)->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'untrusted.example',
            'X-Forwarded-Port' => '443',
        ])->get('/_local-http-probe')->assertJson([
            'secure' => false,
            'login_url' => 'http://localhost/login',
            'asset_url' => 'http://localhost/build/probe.css',
        ]);
    }

    public function test_ngrok_preparation_files_keep_vite_manifest_driven(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $script = file_get_contents(base_path('scripts/prepare-ngrok-preview.ps1'));
        $diagnosticScript = file_get_contents(base_path('scripts/diagnose-ngrok-preview.ps1'));
        $view = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString("env('NGROK_PREVIEW', false)", $bootstrap);
        $this->assertStringContainsString("env('APP_ENV', 'production') === 'local'", $bootstrap);
        $this->assertStringContainsString("at: '*'", $bootstrap);
        $this->assertStringContainsString('Request::HEADER_X_FORWARDED_PROTO', $bootstrap);
        $this->assertStringContainsString('npm.cmd run build', $script);
        $this->assertStringContainsString('Join-Path $publicPath \'hot\'', $script);
        $this->assertStringContainsString('php artisan optimize:clear', $script);
        $this->assertStringContainsString('resources/js/app.ts', $script);
        $this->assertStringContainsString('Content-Type JavaScript invalido', $diagnosticScript);
        $this->assertStringContainsString('Get-FileHash', $diagnosticScript);
        $this->assertStringContainsString("'/build/assets/*'", $diagnosticScript);
        $this->assertStringContainsString("@vite('resources/js/app.ts')", $view);
        $this->assertStringNotContainsString('/build/assets/app-', $view);
    }
}
