<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in'
        ]);

        $room = Room::find($request->room_id);

        if ($room->stock <= 0) {
            return response()->json(['message' => 'Kamar tidak tersedia'], 400);
        }

        // hitung harga total
        $days = (strtotime($request->check_out) - strtotime($request->check_in)) / 86400;
        $total = $days * $room->price;

        // buat reservasi
        $reservation = Reservation::create([
            'user_id' => auth()->id(),
            'room_id' => $room->id,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'total_price' => $total,
            'status' => 'pending'
        ]);

        // kurangi stock kamar
        $room->decrement('stock');

        return response()->json([
            'message' => 'Booking berhasil',
            'reservation' => $reservation
        ], 201);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Reservation::with('room')->where('user_id', auth()->id())->get();
    }

    // ADMIN - lihat semua reservasi
    public function all() {
        return Reservation::with(['room', 'user'])->get();
    }

    // USER lihat booking miliknya
    public function myBooking()
    {
        $bookings = Reservation::where('user_id', Auth::id())->with('room')->get();
        return response()->json($bookings);
    }

    // ADMIN & STAFF update status booking
    public function updateStatus(Request $request, $id)
    {
        $booking = Reservation::findOrFail($id);

        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $booking->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status booking diperbarui',
            'data' => $booking
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
