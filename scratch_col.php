<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = \App\Models\Admin::where('email', 'rian.pengelola@sinemu.test')->first();
$klaim = \App\Models\Klaim::find(3);

echo "Before reject: status_verifikasi={$klaim->status_verifikasi}, status_klaim={$klaim->status_klaim}\n";

// Execute request through controller or workflow
$request = \Illuminate\Http\Request::create(
    route('admin.claim-verifications.reject', $klaim),
    'POST',
    [
        'identitas_pelapor_valid' => '1',
        'detail_barang_valid' => '0',
        'kronologi_valid' => '0',
        'bukti_visual_valid' => '0',
        'kecocokan_data_laporan' => '0',
        'catatan_verifikasi_admin' => 'Data tidak cocok dengan barang fisik.',
        'alasan_penolakan' => 'Detail barang dan bukti kepemilikan tidak sesuai dengan fisik barang yang diamankan.',
    ]
);

/** @var \Illuminate\Contracts\Auth\StatefulGuard $adminGuard */
$adminGuard = auth('admin');
$adminGuard->login($admin);

$controller = app(\App\Http\Controllers\Admin\ClaimVerificationController::class);
$formRequest = \App\Http\Requests\Admin\RejectClaimRequest::createFrom($request);
$formRequest->setContainer($app)->setRedirector(app('redirect'));
$formRequest->validateResolved();

$response = $controller->reject($formRequest, $klaim);
echo "Response status: " . $response->getStatusCode() . "\n";
echo "Session status: " . session('status') . "\n";
echo "Session error: " . session('error') . "\n";

$klaim->refresh();
echo "After reject: status_verifikasi={$klaim->status_verifikasi}, status_klaim={$klaim->status_klaim}\n";
echo "Barang status: " . $klaim->barang->status_barang . "\n";
echo "Pencocokan status: " . $klaim->pencocokan?->status_pencocokan . "\n";
