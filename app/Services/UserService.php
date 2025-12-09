<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UserService
{
    public function createStaff(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
    }

    public function updateUser(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function deleteUser(User $user): void
    {
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->delete();
    }

    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']); // Keep old password if not provided
        }

        $user->update($data);

        return $user;
    }

    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        // Delete old avatar
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);
        $image->cover(300, 300);

        $filename = 'profile_image/' . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $image->toJpeg(90));

        $user->update(['profile_image' => $filename]);

        return $filename;
    }
}
