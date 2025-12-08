<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UserController extends Controller
{

    public function index() {
        return response()->json([
            'success' => true,
            'data' => User::all()->map(fn ($u) => $this->formatUserResponse($u))
        ]);
    }

    // admin Create staff
    public function storeStaff(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'staff',
            'email_verified_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'staff berhasil ditambahkan',
            'data' => $this->formatUserResponse($user)
        ], 201);

    }


    // Admin update user
    public function update(Request $request, $id) {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'role' => 'nullable|in:admin,staff,user',
            'password' => 'nullable|min:6',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => $request->password ? Hash::make($request->password) : $user->password
        ]);

        return response()->json([
            'message' => 'User berhasil ditambahkan'
        ]);
    }


    // Admin hapus user
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // mencegah menghapus admin lain
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Forbidden to delete another admin'], 403);
        }

        // mencegah hapus diri sendiri
        if (Auth::id() == $id) {
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }

        // hapus avatar jika ada
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->delete();
        return response()->json(['message' => 'User dberhasil dihapus']);
    }


    // All roles get profile
    public function profile() {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'data' => $this->formatUserResponse($user)
        ]);
    }


    // user dan staff update profile
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'  => 'nullable',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6',
        ]);

        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $this->formatUserResponse($user)
        ]);
    }

    // All roles upload avatar
    public function uploadAvatar(Request $request) {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,jpg,png,svg|max:2548',
        ]);

        $user = auth()->user();

        // Hapus avatar lama jikan ada
        if ($user->profile_image && Storage::exist($user->profile_image)) {
            Storage::delete($user->profile_image);
        }

        $manager = new ImageManager(new Driver());

        $image = $manager->read($request->file('profile_image'));
        $image->cover(300,300);

        $filename = 'profile_image/' . uniqid() . '.jpg';

        Storage::put($filename, $image->toJpeg(90));

        $user->update(['profile_image' => $filename]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil upload avatar',
            'avatar' => $filename
        ]);
    }


    private function formatUserResponse(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null,
            'created_at' => $user->created_at,
        ];
    }
}
