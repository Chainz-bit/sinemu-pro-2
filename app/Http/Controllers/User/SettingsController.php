<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SettingsHistoryIndexRequest;
use App\Http\Requests\User\UpdateSettingsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();

        return view('user.pages.settings', compact('user'));
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $this->currentUser();
        $user->forceFill($request->validated())->save();

        return redirect()
            ->route('user.settings')
            ->with('status', 'Pengaturan akun berhasil diperbarui.');
    }

    public function history(SettingsHistoryIndexRequest $request): View
    {
        $user = $this->currentUser();
        $query = $user->notifications()->latest('created_at');

        $statusFilter = $request->status();
        if ($statusFilter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($statusFilter === 'read') {
            $query->whereNotNull('read_at');
        }

        $typeFilter = $request->typeFilter();
        if ($typeFilter !== '') {
            $query->where('type', $typeFilter);
        }

        $dateFilter = $request->dateFilter();
        if ($dateFilter !== '') {
            $query->whereDate('created_at', $dateFilter);
        }

        $search = $request->search();
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%');
            });
        }

        $histories = $query->paginate(12)->withQueryString();

        $typeOptions = $user->notifications()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->mapWithKeys(function ($type) {
                $normalized = (string) $type;

                return [$normalized => str_replace('_', ' ', ucwords($normalized, '_'))];
            })
            ->all();

        $summary = [
            'total' => $user->notifications()->count(),
            'unread' => $user->notifications()->whereNull('read_at')->count(),
            'read' => $user->notifications()->whereNotNull('read_at')->count(),
        ];

        return view('user.pages.settings-history', compact(
            'user',
            'histories',
            'summary',
            'typeOptions',
            'statusFilter',
            'typeFilter',
            'dateFilter',
            'search'
        ));
    }

    private function currentUser(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(!$user, 403);

        return $user;
    }
}
