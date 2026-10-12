<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): UserResource
    {
        return new UserResource(request()->user());
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $payload['email'] = strtolower((string) $validated['email']);
        }

        if (array_key_exists('username', $validated)) {
            $payload['username'] = $validated['username'];
        }

        if (array_key_exists('phone', $validated)) {
            $payload['nomor_telepon'] = $validated['phone'];
        }

        if (array_key_exists('alamat', $validated)) {
            $payload['alamat'] = $validated['alamat'];
        }

        if ($payload !== []) {
            $user->forceFill($payload)->save();
        }

        return response()->json([
            'message' => 'Profile berhasil diperbarui',
            'user' => new UserResource($user->refresh()),
        ]);
    }
}
