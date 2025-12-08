<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | USER - CREATE BOOKING + MIDTRANS
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'room_id'   => 'required|exists:rooms,id',
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after:check_in'
        ]);

        DB::beginTransaction();

        try {
            $room = Room::lockForUpdate()->findOrFail($request->room_id);

            if ($room->stock <= 0) {
                return response()->json(['message' => 'Kamar tidak tersedia'], 400);
            }

            $days = (strtotime($request->check_out) - strtotime($request->check_in)) / 86400;
            $total = $days * $room->price;

            $orderId = 'BOOK-' . time();

            $reservation = Reservation::create([
                'user_id' => auth()->id(),
                'room_id' => $room->id,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'total_price' => $total,
                'status' => 'pending',
                'payment_status' => 'pending',
                'transaction_id' => $orderId
            ]);

            $room->decrement('stock');

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Booking gagal'], 500);
        }

        // MIDTRANS CONFIG
        \Midtrans\Config::$serverKey = config('midtrans.serverKey');
        \Midtrans\Config::$isProduction = config('midtrans.isProduction');
        \Midtrans\Config::$is3ds = true;

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'name'  => auth()->user()->name,
                'email' => auth()->user()->email,
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

        $snap = \Midtrans\Snap::createTransaction($params);
        $paymentUrl = $snap->redirect_url;

        $reservation->update([
            'payment_url' => $paymentUrl
        ]);

        return response()->json([
            'message' => 'Booking berhasil — lanjutkan pembayaran',
            'reservation' => $reservation,
            'payment_url' => $paymentUrl
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | USER - MY BOOKINGS
    |--------------------------------------------------------------------------
    */
    public function myBooking()
    {
        return Reservation::where('user_id', Auth::id())
            ->with('room')
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | USER & ADMIN - DETAIL BOOKING
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $reservation = Reservation::with(['room', 'user'])->findOrFail($id);

        if (Auth::user()->role === 'user' && $reservation->user_id !== Auth::id()) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        return response()->json($reservation);
    }

    /*
    |--------------------------------------------------------------------------
    | USER - CANCEL BOOKING
    |--------------------------------------------------------------------------
    */
    public function cancel($id)
    {
        $reservation = Reservation::where('user_id', Auth::id())->findOrFail($id);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Booking tidak bisa dibatalkan'], 400);
        }

        DB::beginTransaction();

        try {
            $reservation->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled'
            ]);

            $reservation->room->increment('stock');

            DB::commit();

            return response()->json(['message' => 'Booking berhasil dibatalkan']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membatalkan booking'], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - ALL BOOKINGS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        return Reservation::with(['room', 'user'])
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - UPDATE STATUS OPERASIONAL
    |--------------------------------------------------------------------------
    */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,checked_in,checked_out,cancelled'
        ]);

        $booking = Reservation::findOrFail($id);
        $booking->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status booking diperbarui',
            'data' => $booking
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MIDTRANS CALLBACK
    |--------------------------------------------------------------------------
    */
    public function callback(Request $request)
    {
        $serverKey = config('midtrans.serverKey');

        $hashed = hash('sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if ($hashed !== $request->signature_key) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $reservation = Reservation::where('transaction_id', $request->order_id)->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $transaction = $request->transaction_status;

        if ($transaction == 'settlement') {
            $reservation->update([
                'payment_status' => 'paid',
                'status' => 'approved'
            ]);
        }

        if ($transaction == 'expire') {
            $reservation->update([
                'payment_status' => 'expired',
                'status' => 'cancelled'
            ]);

            $reservation->room->increment('stock');
        }

        if (in_array($transaction, ['cancel', 'deny', 'failure'])) {
            $reservation->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);

            $reservation->room->increment('stock');
        }

        return response()->json(['message' => 'Callback processed']);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN - ALL RESERVATIONS
    |--------------------------------------------------------------------------
    */
    public function all()
    {
        return Reservation::with(['room', 'user'])->latest()->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN - DASHBOARD STATS
    |--------------------------------------------------------------------------
    */
    public function stats()
    {
        return response()->json([
            'total_users' => DB::table('users')->count(),
            'total_rooms' => DB::table('rooms')->count(),
            'total_bookings' => Reservation::count(),
            'total_income' =>
                Reservation::where('payment_status', 'paid')->sum('total_price'),
            'monthly_income' =>
                Reservation::whereMonth('created_at', now()->month)
                    ->where('payment_status', 'paid')
                    ->sum('total_price')
        ]);
    }
}
