<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Exception;

class ReservationService
{
    protected $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function createReservation(array $data, int $userId): Reservation
    {
        return DB::transaction(function () use ($data, $userId) {
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);

            if ($room->stock <= 0) {
                throw new Exception('Kamar tidak tersedia', 400);
            }

            $days = (strtotime($data['check_out']) - strtotime($data['check_in'])) / 86400;
            $total = $days * $room->price;
            $orderId = 'BOOK-' . time();

            $reservation = Reservation::create([
                'user_id' => $userId,
                'room_id' => $room->id,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'total_price' => $total,
                'status' => ReservationStatus::PENDING->value,
                'payment_status' => PaymentStatus::PENDING->value,
                'transaction_id' => $orderId
            ]);

            $room->decrement('stock');

            // Generate Payment URL
            $paymentUrl = $this->generatePaymentUrl($reservation, $room, $days, $orderId, $total);
            
            $reservation->update(['payment_url' => $paymentUrl]);

            return $reservation;
        });
    }

    protected function generatePaymentUrl(Reservation $reservation, Room $room, int $days, string $orderId, float $total): string
    {
        $user = $reservation->user;

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'name'  => $user->name ?? 'Guest',
                'email' => $user->email ?? 'guest@example.com',
            ],
            'item_details' => [
                [
                    'id' => $room->id,
                    'price' => $room->price,
                    'quantity' => $days,
                    'name' => $room->name,
                ],
            ],
        ];

        return $this->paymentGateway->generatePaymentUrl($params);
    }
}
