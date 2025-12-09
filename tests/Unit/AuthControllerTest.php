<?php

namespace Tests\Unit;

use App\Http\Controllers\AuthController;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Mockery;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_register_delegates_to_auth_service()
    {
        // Mock Data
        $userData = [
            'name' => 'Unit Test',
            'email' => 'unit@example.com',
            'password' => 'password',
            'role' => 'staff'
        ];

        $user = new User($userData);

        // Mock Service
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('register')
            ->once()
            ->with($userData)
            ->andReturn($user);

        // Mock Request
        $request = Mockery::mock(RegisterRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($userData);

        // Instantiate Controller
        $controller = new AuthController($authService);

        // Execute
        $response = $controller->register($request);
        $data = $response->getData(true);

        // Assert
        $this->assertEquals('Registrasi Berhasil, silahkan Verifikasi email Anda', $data['message']);
        $this->assertEquals('staff', $data['user']['role']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
