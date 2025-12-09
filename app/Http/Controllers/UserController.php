<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index() {
        return response()->json([
            'success' => true,
            'data' => UserResource::collection(User::all())
        ]);
    }

    // admin Create staff
    public function storeStaff(StoreStaffRequest $request) {
        $user = $this->userService->createStaff($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Staff berhasil ditambahkan',
            'data' => new UserResource($user)
        ], 201);
    }

    // Admin update user
    public function update(UpdateUserRequest $request, $id) {
        $user = User::findOrFail($id);
        $user = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data' => new UserResource($user)
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

        $this->userService->deleteUser($user);

        return response()->json(['message' => 'User berhasil dihapus']);
    }

    // All roles get profile
    public function profile() {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    // user dan staff update profile
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $user = $this->userService->updateProfile($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user)
        ]);
    }

    // All roles upload avatar
    public function uploadAvatar(UploadAvatarRequest $request) {
        $user = auth()->user();
        $filename = $this->userService->uploadAvatar($user, $request->file('profile_image'));

        return response()->json([
            'success' => true,
            'message' => 'Berhasil upload avatar',
            'avatar' => $filename
        ]);
    }
}
