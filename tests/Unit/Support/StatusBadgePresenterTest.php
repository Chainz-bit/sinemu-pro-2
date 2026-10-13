<?php

namespace Tests\Unit\Support;

use App\Support\StatusBadgePresenter;
use App\Support\WorkflowStatus;
use PHPUnit\Framework\TestCase;

class StatusBadgePresenterTest extends TestCase
{
    public function test_resolve_claim_status_returns_semantic_colors_and_labels(): void
    {
        $selesai = StatusBadgePresenter::resolve(WorkflowStatus::CLAIM_COMPLETED, 'claim');
        $this->assertSame('selesai', $selesai['key']);
        $this->assertSame('SELESAI', $selesai['label']);
        $this->assertSame('success', $selesai['semantic']);
        $this->assertStringContainsString('bg-emerald-50', $selesai['color']);

        $ditolak = StatusBadgePresenter::resolve(WorkflowStatus::CLAIM_REJECTED, 'claim');
        $this->assertSame('ditolak', $ditolak['key']);
        $this->assertSame('DITOLAK', $ditolak['label']);
        $this->assertSame('danger', $ditolak['semantic']);
        $this->assertStringContainsString('bg-rose-50', $ditolak['color']);

        $disetujui = StatusBadgePresenter::resolve(WorkflowStatus::CLAIM_APPROVED, 'claim');
        $this->assertSame('disetujui', $disetujui['key']);
        $this->assertSame('TERVERIFIKASI', $disetujui['label']);
        $this->assertSame('warning', $disetujui['semantic']);
        $this->assertStringContainsString('bg-amber-50', $disetujui['color']);

        $menunggu = StatusBadgePresenter::resolve(WorkflowStatus::CLAIM_UNDER_REVIEW, 'claim');
        $this->assertSame('menunggu', $menunggu['key']);
        $this->assertSame('MENUNGGU VERIFIKASI', $menunggu['label']);
        $this->assertSame('info', $menunggu['semantic']);
        $this->assertStringContainsString('bg-blue-50', $menunggu['color']);
    }

    public function test_resolve_report_status(): void
    {
        $approved = StatusBadgePresenter::resolve(WorkflowStatus::REPORT_APPROVED, 'report');
        $this->assertSame('DISETUJUI', $approved['label']);
        $this->assertSame('success', $approved['semantic']);

        $rejected = StatusBadgePresenter::resolve(WorkflowStatus::REPORT_REJECTED, 'report');
        $this->assertSame('DITOLAK', $rejected['label']);
        $this->assertSame('danger', $rejected['semantic']);

        $matched = StatusBadgePresenter::resolve(WorkflowStatus::REPORT_MATCHED, 'report');
        $this->assertSame('SEDANG DICOCOKKAN', $matched['label']);
        $this->assertSame('info', $matched['semantic']);
    }

    public function test_resolve_found_item_status(): void
    {
        $available = StatusBadgePresenter::resolve(WorkflowStatus::FOUND_AVAILABLE, 'found_item');
        $this->assertSame('TERSEDIA', $available['label']);
        $this->assertSame('info', $available['semantic']);

        $inProgress = StatusBadgePresenter::resolve(WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, 'found_item');
        $this->assertSame('DALAM PROSES KLAIM', $inProgress['label']);
        $this->assertSame('warning', $inProgress['semantic']);

        $claimed = StatusBadgePresenter::resolve(WorkflowStatus::FOUND_CLAIMED, 'found_item');
        $this->assertSame('SUDAH DIKLAIM', $claimed['label']);
        $this->assertSame('success', $claimed['semantic']);
    }

    public function test_resolve_admin_status(): void
    {
        $active = StatusBadgePresenter::resolve('active', 'admin');
        $this->assertSame('Aktif', $active['label']);
        $this->assertSame('success', $active['semantic']);

        $pending = StatusBadgePresenter::resolve('pending', 'admin');
        $this->assertSame('Menunggu', $pending['label']);
        $this->assertSame('warning', $pending['semantic']);

        $rejected = StatusBadgePresenter::resolve('rejected', 'admin');
        $this->assertSame('Ditolak', $rejected['label']);
        $this->assertSame('danger', $rejected['semantic']);

        $inactive = StatusBadgePresenter::resolve('inactive', 'admin');
        $this->assertSame('Nonaktif', $inactive['label']);
        $this->assertSame('neutral', $inactive['semantic']);
    }

    public function test_resolve_log_status(): void
    {
        $unread = StatusBadgePresenter::resolve('unread', 'log');
        $this->assertSame('Belum Dibaca', $unread['label']);
        $this->assertSame('info', $unread['semantic']);

        $read = StatusBadgePresenter::resolve('read', 'log');
        $this->assertSame('Sudah Dibaca', $read['label']);
        $this->assertSame('success', $read['semantic']);
    }

    public function test_badge_classes_combines_base_size_and_colors(): void
    {
        $smClasses = StatusBadgePresenter::badgeClasses('active', 'admin', 'sm');
        $this->assertStringContainsString('inline-flex items-center justify-center font-semibold rounded-full whitespace-nowrap', $smClasses);
        $this->assertStringContainsString('px-3 py-1 text-xs', $smClasses);
        $this->assertStringContainsString('bg-emerald-50 text-emerald-700', $smClasses);

        $mdClasses = StatusBadgePresenter::badgeClasses('pending', 'admin', 'md');
        $this->assertStringContainsString('px-3.5 py-1.5 text-xs sm:text-sm', $mdClasses);
        $this->assertStringContainsString('bg-amber-50 text-amber-700', $mdClasses);
    }
}
