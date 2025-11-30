<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReservationTest extends TestCase
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

    public function test_user_can_make_reservation() {
        // Buat user
        $user = User::factory()->create([
            'password' => bcrypt('123456')
        ]);

        // Login dapat token
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => '123456'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse['token'];

        // Buat kamar
        $room = Room::create([
            'name' => 'Deluxe Room',
            'description' => 'Kamar nyaman dengan AC',
            'price' => 300000,
            'stock' => 3
        ]);

        // Request booking
        $reservationResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/reservations', [
                'room_id' => $room->id,
                'check_in' => '2025-01-01',
                'check_out' => '2025-01-05'
            ]);

        $reservationResponse->assertStatus(201);
        $reservationResponse->assertJson([
            'message' => 'Booking berhasil'
        ]);

        // Pastikan stok kamar berkurang 1
        $this->assertEquals(2, $room->fresh()->stock);
    }

    public function test_reservation_fails_if_no_stock() {
        // Buat user
        $user = User::factory()->create([
            'password' => bcrypt('123456')
        ]);

        // Login dapat token
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => '123456'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse['token'];

        // Buat kamar
        $room = Room::create([
            'name' => 'Deluxe Room',
            'description' => 'Kamar nyaman dengan AC',
            'price' => 300000,
            'stock' => 0
        ]);

        // Request booking
        $reservationResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/reservations', [
                'room_id' => $room->id,
                'check_in' => '2025-01-01',
                'check_out' => '2025-01-05'
            ]);

        $reservationResponse->assertStatus(400);

    }

    public function test_unauthenticated_uesrs_cannot_make_reservation() {
        $room = Room::factory()->create();
        $response = $this->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in' => '2025-01-01',
            'check_out' => '2025-01-03'
        ]);

        $response->assertStatus(401);
    }

}
