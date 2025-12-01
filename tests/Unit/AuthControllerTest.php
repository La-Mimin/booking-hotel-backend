<?php

namespace Tests\Unit;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_register_method_creates_user()
    {
        $controller = new AuthController();

        $request = new Request([
            'name' => 'Unit Test',
            'email' => 'unit@example.com',
            'password' => 'password',
            'role' => 'staff'
        ]);

        // tambahkan validasi manual untuk meniru lifecycle Laravel
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'in:admin,staff,user'
        ]);

        $this->assertFalse($validator->fails());

        $response = $controller->register($request);
        $data = $response->getData(true);

        $this->assertEquals('Registrasi berhasil', $data['message']);
        $this->assertEquals('staff', $data['user']['role']);

        $this->assertDatabaseHas('users', [
            'email' => 'unit@example.com'
        ]);
    }
}
