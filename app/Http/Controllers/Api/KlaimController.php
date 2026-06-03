<?php

namespace App\Http\Controllers\Api;

use App\Actions\Claims\SubmitClaimAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreKlaimRequest;
use App\Http\Resources\Api\KlaimResource;
use App\Models\Barang;
use App\Models\Klaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KlaimController extends Controller
{
    public function store(StoreKlaimRequest $request, Barang $barang, SubmitClaimAction $submitClaimAction): JsonResponse
    {
        $validated = $request->validated();
        $validated['barang_id'] = (int) $barang->id;

        if ((int) $barang->user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'Tidak bisa mengklaim barang temuan milik sendiri.',
            ], 403);
        }

        $result = $submitClaimAction->execute(
            $validated,
            $request->file('bukti_foto', []),
            $request->user()
        );
        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
            ], $result['status'] ?? 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new KlaimResource($result['claim']->load(['barang:id,nama_barang', 'laporanHilang:id,nama_barang,kontak_pelapor', 'user:id,nomor_telepon'])),
        ], 201);
    }

    public function index(): AnonymousResourceCollection
    {
        $klaims = Klaim::query()
            ->where('user_id', (int) request()->user()->id)
            ->with(['barang:id,nama_barang', 'laporanHilang:id,nama_barang,kontak_pelapor', 'user:id,nomor_telepon'])
            ->latest('created_at')
            ->get();

        return KlaimResource::collection($klaims);
    }

    public function show(Klaim $klaim): KlaimResource
    {
        $this->ensureOwner($klaim);

        return new KlaimResource($klaim->load(['barang:id,nama_barang', 'laporanHilang:id,nama_barang,kontak_pelapor', 'user:id,nomor_telepon']));
    }

    private function ensureOwner(Klaim $klaim): void
    {
        if ((int) $klaim->user_id !== (int) request()->user()->id) {
            abort(403, 'Tidak punya akses untuk data ini.');
        }
    }
}
