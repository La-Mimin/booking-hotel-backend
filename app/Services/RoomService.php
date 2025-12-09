<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RoomService
{
    public function createRoom(array $data): Room
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $data['image']->store('rooms', 'public');
        }

        return Room::create($data);
    }

    public function updateRoom(Room $room, array $data): Room
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($room->image && Storage::disk('public')->exists($room->image)) {
                Storage::disk('public')->delete($room->image);
            }
            $data['image'] = $data['image']->store('rooms', 'public');
        }

        $room->update($data);

        return $room;
    }

    public function deleteRoom(Room $room): void
    {
        if ($room->image && Storage::disk('public')->exists($room->image)) {
            Storage::disk('public')->delete($room->image);
        }

        $room->delete();
    }
}
