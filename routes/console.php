<?php

use App\Services\Support\ClaimEvidenceAuditService;
use App\Services\Support\ClaimEvidenceMigrationService;
use App\Services\Support\LegacyScopeAuditService;
use App\Services\Support\UploadFileAuditService;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('super-admin:create
    {--name= : Nama super admin}
    {--email= : Email super admin}
    {--username= : Username super admin}
    {--password= : Password super admin}
    {--random-password : Generate password acak}
    {--force : Update akun jika email/username sudah ada}', function () {
    $name = trim((string) ($this->option('name') ?? ''));
    $email = trim(strtolower((string) ($this->option('email') ?? '')));
    $username = trim(strtolower((string) ($this->option('username') ?? '')));
    $passwordOption = (string) ($this->option('password') ?? '');
    $randomPassword = (bool) $this->option('random-password');
    $force = (bool) $this->option('force');

    if ($name === '') {
        $name = trim((string) $this->ask('Nama super admin'));
    }
    if ($email === '') {
        $email = trim(strtolower((string) $this->ask('Email super admin')));
    }
    if ($username === '') {
        $username = trim(strtolower((string) $this->ask('Username super admin')));
    }

    $passwordPlain = $passwordOption;
    if ($randomPassword) {
        $passwordPlain = Str::password(16, true, true, false, false);
    }
    if ($passwordPlain === '') {
        $passwordPlain = (string) $this->secret('Password super admin (minimal 8 karakter)');
    }

    $validator = Validator::make(
        [
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $passwordPlain,
        ],
        [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }
        return self::FAILURE;
    }

    $existing = SuperAdmin::query()
        ->where('email', $email)
        ->orWhere('username', $username)
        ->first();

    if ($existing && !$force) {
        $this->error('Super admin dengan email/username tersebut sudah ada. Gunakan --force untuk update.');
        return self::FAILURE;
    }

    $payload = [
        'nama' => $name,
        'email' => $email,
        'username' => $username,
        'password' => Hash::make($passwordPlain),
    ];

    if ($existing) {
        $existing->forceFill($payload)->save();
        $superAdmin = $existing;
        $action = 'updated';
    } else {
        $superAdmin = SuperAdmin::query()->create($payload);
        $action = 'created';
    }

    $this->info('Super admin berhasil di-' . $action . '.');
    $this->line('ID       : ' . $superAdmin->id);
    $this->line('Nama     : ' . $superAdmin->nama);
    $this->line('Email    : ' . $superAdmin->email);
    $this->line('Username : ' . $superAdmin->username);

    if ($randomPassword) {
        $this->warn('Password acak: ' . $passwordPlain);
    }

    return self::SUCCESS;
})->purpose('Create or update super admin account securely (internal use only)');

Artisan::command('sinemu:audit-legacy-scope
    {--sample=20 : Jumlah contoh ID yang ditampilkan per temuan}', function (LegacyScopeAuditService $auditService) {
    $sampleLimit = max(1, min((int) $this->option('sample'), 100));
    $audit = $auditService->audit($sampleLimit);

    $this->info('SINEMU legacy scope audit');
    $this->line('Mode: read-only, tidak mengubah data.');
    $this->line('Sample limit: ' . $sampleLimit);
    $this->newLine();

    foreach ($audit['tables'] as $table => $result) {
        $this->line('[' . $table . ']');

        if (($result['exists'] ?? false) !== true) {
            $this->warn('exists: no');
            $this->newLine();
            continue;
        }

        foreach ($result as $key => $value) {
            if ($key === 'exists') {
                continue;
            }

            if (is_array($value)) {
                $this->line($key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }

            $this->line($key . ': ' . ($value === null ? 'n/a' : (string) $value));
        }

        $this->newLine();
    }

    $this->line('[mapping_fields]');
    foreach ($audit['mapping_fields'] as $field) {
        $this->line('- ' . $field);
    }

    $this->newLine();
    $this->line('[recommendations]');
    foreach ($audit['recommendations'] as $recommendation) {
        $this->line('- ' . $recommendation);
    }

    return self::SUCCESS;
})->purpose('Audit legacy barang/laporan/klaim scope data without changing records');

Artisan::command('sinemu:audit-upload-files
    {--sample=20 : Jumlah contoh path yang ditampilkan per temuan}', function (UploadFileAuditService $auditService) {
    $sampleLimit = max(1, min((int) $this->option('sample'), 100));
    $audit = $auditService->audit($sampleLimit);

    $this->info('SINEMU upload file audit');
    $this->line('Mode: read-only, tidak mengubah file atau data.');
    $this->line('Sample limit: ' . $sampleLimit);
    $this->newLine();

    foreach ($audit as $key => $value) {
        if (is_array($value)) {
            $this->line($key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            continue;
        }

        $this->line($key . ': ' . (string) $value);
    }

    return self::SUCCESS;
})->purpose('Audit uploaded image references and storage files without changing records');

Artisan::command('sinemu:audit-claim-evidence-files
    {--sample=20 : Jumlah contoh path yang ditampilkan per temuan}', function (ClaimEvidenceAuditService $auditService) {
    $sampleLimit = max(1, min((int) $this->option('sample'), 100));
    $audit = $auditService->audit($sampleLimit);

    $this->info('SINEMU claim evidence file audit');
    $this->line('Mode: read-only, tidak mengubah file atau data.');
    $this->line('Sample limit: ' . $sampleLimit);
    $this->newLine();

    foreach ($audit as $key => $value) {
        if (is_array($value)) {
            $this->line($key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            continue;
        }

        $this->line($key . ': ' . (string) $value);
    }

    return self::SUCCESS;
})->purpose('Audit claim evidence file paths without moving or deleting files');

Artisan::command('sinemu:migrate-claim-evidence-to-private
    {--dry-run : Tampilkan rencana migrasi tanpa mengubah file atau database}
    {--execute : Copy file legacy public ke private dan update path database}
    {--limit= : Batasi jumlah klaim yang discan}
    {--claim-id= : Migrasikan satu klaim tertentu}
    {--sample=20 : Jumlah contoh path/warning yang ditampilkan}', function (ClaimEvidenceMigrationService $migrationService) {
    $execute = (bool) $this->option('execute');
    $dryRun = (bool) $this->option('dry-run') || !$execute;
    $limitOption = $this->option('limit');
    $claimIdOption = $this->option('claim-id');
    $sampleLimit = max(1, min((int) $this->option('sample'), 100));

    $limit = is_null($limitOption) || $limitOption === '' ? null : max(1, (int) $limitOption);
    $claimId = is_null($claimIdOption) || $claimIdOption === '' ? null : max(1, (int) $claimIdOption);

    $summary = $migrationService->migrate(
        execute: $execute && !$dryRun,
        limit: $limit,
        claimId: $claimId,
        sampleLimit: $sampleLimit
    );

    $this->info('SINEMU claim evidence migration to private storage');
    $this->line(($execute && !$dryRun) ? 'EXECUTE MODE' : 'DRY RUN MODE');
    $this->line('Legacy public files are not deleted by this command.');
    $this->newLine();

    foreach ($summary as $key => $value) {
        if ($key === 'mode') {
            continue;
        }

        if (is_array($value)) {
            $this->line($key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            continue;
        }

        $this->line($key . ': ' . (string) $value);
    }

    if (!$execute || $dryRun) {
        $this->newLine();
        $this->warn('Use --execute to apply migration.');
    }

    return self::SUCCESS;
})->purpose('Dry-run or migrate legacy claim evidence files from public to private storage');
