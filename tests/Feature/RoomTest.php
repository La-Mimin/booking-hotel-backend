<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    // public function test_example(): void
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }

    public function test_room_crud() {
        // 1. Register User
        $register = $this->postJson('api/register', [
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => 'password',
        ]);

        $register->assertStatus(201);

        // 2. Login User
        $login = $this->postJson('api/login', [
            'email' => 'test@gmail.com',
            'password' => 'password',
        ]);

        $login->assertStatus(200);
        $token = $login->json('token');

        // 3. Create room
        $create = $this->postJson('/api/rooms', [
            'name' => 'Deluxe Room 3',
            'description' => 'Kamar bagus',
            'price' => 500000,
            'stock' => 5,
        ], [
            'Authorization' => "Bearer $token"
        ]);

        $create->assertStatus(201);
        $roomId = $create->json('id');

        // Upload image to room
        Storage::fake('public');

        $image = UploadedFile::fake()->image('room.jpg');

        $upload = $this->postJson('/api/rooms', [
            'name' => 'Room with Image',
            'description' => 'With image upload',
            'price' => 700000,
            'stock' => 4,
            'image' => $image,
        ], [
            'Authorization' => "Bearer $token"
        ]);

        $upload->assertStatus(201);
        $this->assertNotNull($upload->json('image')); // path tersimpan
        $this->assertNotNull($upload->json('image_url')); // URL tersedia
        Storage::disk('public')->assertExists($upload->json('image'));

        // 4. Read room (GET)
        $read = $this->getJson("/api/rooms/$roomId", [
            'Authorization' => "Bearer $token"
        ]);

        $read->assertStatus(200);

        // 5. Update room
        $update = $this->putJson("/api/rooms/$roomId", [
            'name' => 'Update room',
            'price' => 900000,
            'stock' => 3,
        ], [
            'Authorization' => "Bearer $token"
        ]);

        $update->assertStatus(200);
        $this->assertEquals('Update room', $update->json('name'));

        // 6. Delete room
        $delete = $this->deleteJson("/api/rooms/$roomId", [], [
            'Authorization' => "Bearer $token"
        ]);

        $delete->assertStatus(200);
        $this->assertEquals('Room deleted', $delete->json('message'));
    }

}
