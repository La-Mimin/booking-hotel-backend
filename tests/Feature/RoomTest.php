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
            'name' => 'Test User123',
            'email' => 'test123@gmail.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $register->assertStatus(201);
        
        // Manually verify user
        $user = \App\Models\User::where('email', 'test123@gmail.com')->first();
        $user->email_verified_at = now();
        $user->save();

        // 2. Login User
        $login = $this->postJson('api/login', [
            'email' => 'test123@gmail.com',
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
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json'
        ]);

        $create->assertStatus(201);
        $roomId = $create->json('data.id');
        
        // Update token if refreshed
        $newToken = $create->headers->get('Authorization');
        if ($newToken) {
            $token = str_replace('Bearer ', '', $newToken);
        }

        // Upload image to room
        Storage::fake('public');

        $image = UploadedFile::fake()->image('room.jpg');

        // Use post for multipart/form-data, but expect JSON
        $upload = $this->post('/api/rooms', [
            'name' => 'Room with Image',
            'description' => 'With image upload',
            'price' => 700000,
            'stock' => 4,
            'image' => $image,
        ], [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json'
        ]);

        if ($upload->headers->get('Authorization')) {
            $token = str_replace('Bearer ', '', $upload->headers->get('Authorization'));
        }

        $upload->assertStatus(201);
        $this->assertNotNull($upload->json('data.image_url')); // URL tersedia
        
        // Check DB for image path since Resource might not return it directly or check logic
        $uploadedRoom = \App\Models\Room::find($upload->json('data.id'));
        $this->assertNotNull($uploadedRoom->image);
        Storage::disk('public')->assertExists($uploadedRoom->image);

        // 4. Read room (GET)
        $read = $this->getJson("/api/rooms/$roomId", [
            'Authorization' => "Bearer $token"
        ]);

        if ($read->headers->get('Authorization')) {
            $token = str_replace('Bearer ', '', $read->headers->get('Authorization'));
        }

        $read->assertStatus(200);

        // 5. Update room
        $update = $this->putJson("/api/rooms/$roomId", [
            'name' => 'Update room',
            'price' => 900000,
            'stock' => 3,
        ], [
            'Authorization' => "Bearer $token"
        ]);

        if ($update->headers->get('Authorization')) {
            $token = str_replace('Bearer ', '', $update->headers->get('Authorization'));
        }

        $update->assertStatus(200);
        $this->assertEquals('Update room', $update->json('data.name'));

        // 6. Delete room
        $delete = $this->deleteJson("/api/rooms/$roomId", [], [
            'Authorization' => "Bearer $token"
        ]);

        $delete->assertStatus(200);
        $this->assertEquals('Kamar berhasil dihapus', $delete->json('message'));
    }

}
