<?php

namespace App\Services\User\Profile;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Common\ProfileAvatarService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UserProfileEditService
{
    public function __construct(private readonly ProfileAvatarService $avatarService)
    {
    }

    /**
     * @return array<string,string>
     */
    public function buildEditData(User $user): array
    {
        return [
            'profileAvatar' => $this->avatarService->resolve((string) ($user->profil ?? '')),
            'verificationLabel' => 'Aktif',
            'verificationClass' => 'is-active',
        ];
    }

    public function update(User $user, ProfileUpdateRequest $request): void
    {
        $validated = $request->validated();
        unset($validated['profil']);
        if (!Schema::hasColumn('users', 'nomor_telepon')) {
            unset($validated['nomor_telepon']);
        }

        $user->fill($validated);

        $photo = $request->file('profil');
        $oldProfilePath = null;
        $newProfilePath = null;
        if ($photo) {
            $oldProfilePath = $this->deletablePublicPath((string) ($user->profil ?? ''));
            $newProfilePath = $photo->store('profil-user/' . now()->format('Y/m'), 'public');
            $user->profil = $newProfilePath;
        }

        try {
            $user->save();
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
