<?php

namespace App\Services\User\Profile;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Common\ProfileAvatarService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        if ($photo) {
            $oldProfilePath = trim((string) ($user->profil ?? ''));
            if (
                $oldProfilePath !== ''
                && !str_starts_with($oldProfilePath, 'http://')
                && !str_starts_with($oldProfilePath, 'https://')
                && !str_starts_with($oldProfilePath, '/')
            ) {
                Storage::disk('public')->delete($oldProfilePath);
            }

            $user->profil = $photo->store('profil-user/' . now()->format('Y/m'), 'public');
        }

        $user->save();
    }
}
