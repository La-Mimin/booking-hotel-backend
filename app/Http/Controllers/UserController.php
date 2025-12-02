<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin')->only(['storeStaff', 'update', 'destroy', 'index']);
    }

    public function index() {
        return response()->json([
            'success' => true,
            'data' => User::all()->map(fn ($u) => $this->formatUserResponse($u))
        ]);
    }

    public function profile()
    {
        return $this->success('Profile fetched successfully', $this->formatUserResponse(auth()->user()));

    }

    // admin Add user
    public function storeStaff(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'profile_image' => 'nullable|image|max:3048'
        ]);

        $data = $request->only(['name', 'email']);
        $data['password'] = Hash::make($request->password);
        $data['role'] = 'staff';

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();

            $image = Image::read($file);

            // Resize (square 300x300) & compress
            $image->resize(300, 300)->toJpeg(80);

            $path = 'avatars/' . $filename;
            Storage::disk('public')->put($path, (string) $image->encode());

            $data['profile_image'] = $path;
        }

        $staff = User::create($data);

        return $this->success(
            'Staff created successfully',
            $this->formatUserResponse($staff),
            201
        );
    }

    // Update role, avatar atau data user (admin)
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'  => 'nullable',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'role'  => 'nullable|in:admin,staff,user',
            'profile_image' => 'nullable|image|max:3048',
            'password' => 'nullable|min:6',
        ]);

        $data = $request->only(['name', 'email', 'role']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();

            $image = Image::read($file);

            // Resize (square 300x300) & compress
            $image->resize(300, 300)->toJpeg(80);

            $path = 'avatars/' . $filename;
            Storage::disk('public')->put($path, (string) $image->encode());

            $data['profile_image'] = $path;
        }

        $user->update($data);

        return $this->success(
            'User updated successfully',
            $this->formatUserResponse($user)
        );

    }

    // user dan staff update profile
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'  => 'nullable',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6',
            'profile_image' => 'nullable|image|max:3048'
        ]);

        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();

            $image = Image::read($file);

            // Resize (square 300x300) & compress
            $image->resize(300, 300)->toJpeg(80);

            $path = 'avatars/' . $filename;
            Storage::disk('public')->put($path, (string) $image->encode());

            $data['profile_image'] = $path;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $this->formatUserResponse($user)
        ]);
    }

    // Hapus user (admin)
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
        return response()->json(['message' => 'User deleted successfully']);
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

    private function success($message, $data = null, $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    private function error($message, $status = 400)
    {
        return $this->success('User deleted successfully');

    }

}
