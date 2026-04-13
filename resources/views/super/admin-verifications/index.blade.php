@extends('layouts.auth')

@section('content')
<main style="min-height:100vh;padding:16px;background:linear-gradient(160deg,#eff6ff,#f8fafc);font-family:'Plus Jakarta Sans',sans-serif;">
    <div style="max-width:1100px;margin:0 auto;">
        <section style="background:#fff;border:1px solid #dbe5f2;border-radius:16px;padding:16px;box-shadow:0 18px 38px rgba(15,23,42,.12);">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                <div>
                    <h1 style="margin:0;font-size:1.3rem;color:#0f2f63;">Dashboard Verifikasi Admin</h1>
                    <p style="margin:4px 0 0;color:#64748b;font-size:.9rem;">Kelola status admin: pending, active, rejected</p>
                </div>
                <form method="POST" action="{{ route('super.logout') }}">
                    @csrf
                    <button type="submit" style="border:1px solid #dbe5f2;background:#fff;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;color:#334155;">Logout Super Admin</button>
                </form>
            </div>

            @if (session('status'))
                <div style="margin-bottom:10px;background:#ecfeff;color:#0f766e;border:1px solid #a5f3fc;border-radius:10px;padding:10px;font-size:.86rem;">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div style="margin-bottom:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:.86rem;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div style="display:grid;gap:10px;">
                @forelse($admins as $admin)
                    @php
                        $status = $admin->status_verifikasi ?? 'pending';
                        $statusLabel = match($status) {
                            'active' => 'Aktif',
                            'rejected' => 'Ditolak',
                            default => 'Menunggu',
                        };
                        $statusStyle = match($status) {
                            'active' => 'background:#ecfdf5;color:#166534;border:1px solid #86efac;',
                            'rejected' => 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                            default => 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;',
                        };
                    @endphp
                    <article style="border:1px solid #dbe5f2;border-radius:12px;padding:12px;background:#fff;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                            <h2 style="margin:0;font-size:1rem;color:#0f172a;">{{ $admin->nama }} ({{ $admin->username }})</h2>
                            <span style="font-size:.75rem;font-weight:800;padding:4px 9px;border-radius:999px;{{ $statusStyle }}">{{ $statusLabel }}</span>
                        </div>

                        <div style="display:grid;gap:4px;font-size:.87rem;color:#334155;margin-bottom:10px;">
                            <span><strong>Email:</strong> {{ $admin->email }}</span>
                            <span><strong>Instansi:</strong> {{ $admin->instansi }}</span>
                            <span><strong>Kecamatan:</strong> {{ $admin->kecamatan ?? '-' }}</span>
                            <span><strong>Alamat Lengkap:</strong> {{ $admin->alamat_lengkap ?? '-' }}</span>
                            @if($status === 'rejected' && $admin->alasan_penolakan)
                                <span><strong>Alasan Ditolak:</strong> {{ $admin->alasan_penolakan }}</span>
                            @endif
                        </div>

                        @if($status === 'pending')
                            <div style="display:grid;gap:8px;">
                                <form method="POST" action="{{ route('super.admin-verifications.accept', $admin->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" style="border:1px solid #86efac;background:#ecfdf5;color:#166534;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;">Terima</button>
                                </form>

                                <form method="POST" action="{{ route('super.admin-verifications.reject', $admin->id) }}" style="display:grid;gap:6px;">
                                    @csrf
                                    <textarea name="alasan_penolakan" rows="2" placeholder="Alasan penolakan (opsional)" style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px;font-size:.86rem;"></textarea>
                                    <button type="submit" style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;">Tolak</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p style="margin:0;padding:12px;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b;font-size:.9rem;">Belum ada pendaftaran admin.</p>
                @endforelse
            </div>

            <div style="margin-top:10px;">{{ $admins->links() }}</div>
        </section>
    </div>
</main>
@endsection
