@extends('manager::layouts.app')

@php
    $managerRoleLabel = \App\Support\RoleLabels::manager();
    $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();
    $pageTitle = 'Barang Wilayah - ' . $managerRoleLabel;
    $activeMenu = 'found-items';
    $searchAction = manager_route('barang-wilayah.index');
    $searchPlaceholder = 'Cari barang wilayah';
@endphp

@section('page-content')
    <div class="container-fluid py-3 py-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-1">Barang Wilayah</h1>
                <p class="text-secondary small mb-0">
                    Hanya menampilkan barang pada wilayah {{ $managerRoleLabelLower }}: <strong>{{ $manager->region?->nama_wilayah ?? 'Belum diatur' }}</strong>.
                </p>
            </div>

            <form method="GET" action="{{ manager_route('barang-wilayah.index') }}" class="d-flex gap-2 w-100 w-md-auto">
                <input type="search" name="search" class="form-control form-control-sm" placeholder="Cari barang..." value="{{ $search }}">
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th class="min-w-[140px]">Status</th>
                                <th>Wilayah</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($barangs as $barang)
                                <tr>
                                    <td class="fw-semibold align-middle">{{ $barang->nama_barang }}</td>
                                    <td class="align-middle">{{ $barang->kategori?->nama_kategori ?? '-' }}</td>
                                    <td class="align-middle">{{ $barang->lokasi_ditemukan }}</td>
                                    <td class="align-middle min-w-[140px]">
                                        <x-status-badge :status="$barang->status_barang" type="found_item" />
                                    </td>
                                    <td class="align-middle">{{ $barang->region?->nama_wilayah ?? '-' }}</td>
                                    <td class="text-end align-middle">
                                        <a href="{{ manager_route('barang-wilayah.edit', $barang) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                        <form method="POST" action="{{ manager_route('barang-wilayah.destroy', $barang) }}" class="d-inline" data-confirm-delete data-confirm-message="Hapus barang ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4">Belum ada barang pada wilayah Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $barangs->links() }}
        </div>
    </div>
@endsection
