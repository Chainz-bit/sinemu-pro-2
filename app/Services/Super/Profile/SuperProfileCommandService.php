<?php

namespace App\Services\Super\Profile;

use App\Http\Requests\Super\UpdateProfileRequest;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SuperProfileCommandService
{
    public function update(SuperAdmin $superAdmin, UpdateProfileRequest $request): void
    {
        $validated = $request->validated();

        unset($validated['current_password'], $validated['password_confirmation']);

        $photo = $request->file('profil');
        $oldProfilePath = null;
        $newProfilePath = null;
        if ($photo) {
            $oldProfilePath = $this->deletablePublicPath((string) ($superAdmin->profil ?? ''));
            $newProfilePath = $photo->store('profil-super/' . now()->format('Y/m'), 'public');
            $validated['profil'] = $newProfilePath;
        } else {
            unset($validated['profil']);
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make((string) $validated['password']);
        } else {
            unset($validated['password']);
        }

        try {
            $superAdmin->forceFill($validated)->save();
        } catch (Throwable $exception) {
            if ($newProfilePath) {
                Storage::disk('public')->delete($newProfilePath);
            }

            throw $exception;
        }

        if ($oldProfilePath) {
            Storage::disk('public')->delete($oldProfilePath);
        }
    }

    private function deletablePublicPath(string $path): ?string
    {
        $path = trim($path);

        return $path !== ''
            && !str_starts_with($path, 'http://')
            && !str_starts_with($path, 'https://')
            && !str_starts_with($path, '/')
            ? $path
            : null;
    }
}
