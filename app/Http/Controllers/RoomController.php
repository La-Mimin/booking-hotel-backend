<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use App\Http\Requests\RoomRequest;
use App\Http\Resources\RoomResource;
use App\Services\RoomService;

class RoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }
    /*
    |--------------------------------------------------------------------------
    | PUBLIC - LIST ALL ROOMS (NO LOGIN)
    |--------------------------------------------------------------------------
    */
    public function publicIndex()
    {
        $rooms = Room::where('stock', '>', 0)->latest()->get();
        return RoomResource::collection($rooms);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC - SHOW ROOM DETAIL
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $room = Room::findOrFail($id);
        return new RoomResource($room);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - LIST ROOMS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $rooms = Room::latest()->get();
        return RoomResource::collection($rooms);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - STORE ROOM
    |--------------------------------------------------------------------------
    */
    public function store(RoomRequest $request)
    {
        try {
            $room = $this->roomService->createRoom($request->validated());

            return response()->json([
                'message' => 'Kamar berhasil ditambahkan',
                'data' => new RoomResource($room)
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - UPDATE ROOM
    |--------------------------------------------------------------------------
    */
    public function update(RoomRequest $request, $id)
    {
        try {
            $room = Room::findOrFail($id);
            $room = $this->roomService->updateRoom($room, $request->validated());

            return response()->json([
                'message' => 'Kamar berhasil diperbarui',
                'data' => new RoomResource($room)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN & STAFF - DELETE ROOM
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        try {
            $room = Room::findOrFail($id);
            $this->roomService->deleteRoom($room);

            return response()->json([
                'message' => 'Kamar berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
