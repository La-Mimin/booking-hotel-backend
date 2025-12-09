<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Http\Request;
use App\Http\Requests\ReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }
    /*
    |--------------------------------------------------------------------------
    | USER - CREATE BOOKING + MIDTRANS
    |--------------------------------------------------------------------------
    */
    public function store(ReservationRequest $request)
    {
        try {
            $reservation = $this->reservationService->createReservation($request->validated(), auth()->id());

            return response()->json([
                'message' => 'Booking berhasil — lanjutkan pembayaran',
                'reservation' => new ReservationResource($reservation),
                'payment_url' => $reservation->payment_url
            ], 201);

        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
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
