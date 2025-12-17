<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User; 
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Dinas\UploadController;
use App\Http\Controllers\Pusdatin\PenilaianSLHD_Controller;
use App\http\Controllers\Pusdatin\PenilaianPenghargaan_Controller;

Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\Auth\AuthController::class, 'register']);
Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');

// --- REGISTER DROPDOWNS (Public) ---
// Provinsi list untuk dropdown
Route::get('/register/provinces', function () {
    return response()->json([
        'data' => \App\Models\Region::where('type', 'provinsi')
            ->orderBy('nama_region')
            ->get(['id', 'nama_region as nama'])
    ]);
});

// Kabupaten/Kota by provinsi untuk dropdown
Route::get('/register/regencies/{provinceId}', function ($provinceId) {
    return response()->json([
        'data' => \App\Models\Region::where('type', 'kabupaten/kota')
            ->where('parent_id', $provinceId)
            ->orderBy('nama_region')
            ->get(['id', 'nama_region as nama'])
    ]);
});

// Dinas Provinsi list
Route::get('/register/dinas/provinsi', function () {
    return response()->json([
        'data' => \App\Models\Dinas::with('region')
            ->whereHas('region', fn($q) => $q->where('type', 'provinsi'))
            ->orderBy('nama_dinas')
            ->get(['id', 'nama_dinas', 'kode_dinas', 'region_id'])
            ->map(fn($d) => [
                'id' => $d->id,
                'nama_dinas' => $d->nama_dinas,
                'region' => $d->region->nama_region ?? null,
            ])
    ]);
});

// Dinas Kab/Kota by provinsi
Route::get('/register/dinas/kabkota/{provinceId}', function ($provinceId) {
    // Get all region_ids untuk kabupaten/kota di bawah provinsi ini
    $regionIds = \App\Models\Region::where('type', 'kabupaten/kota')
        ->where('parent_id', $provinceId)
        ->pluck('id');
    
    return response()->json([
        'data' => \App\Models\Dinas::with('region')
            ->whereIn('region_id', $regionIds)
            ->orderBy('nama_dinas')
            ->get(['id', 'nama_dinas', 'kode_dinas', 'region_id'])
            ->map(fn($d) => [
                'id' => $d->id,
                'nama_dinas' => $d->nama_dinas,
                'region' => $d->region->nama_region ?? null,
            ])
    ]);
});

// Public route untuk download/preview dokumen (tidak perlu auth karena dibuka di new tab)
Route::prefix('document')->group(function () {
    Route::get('/preview/{submission}/{documentType}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'previewDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
    Route::get('/download/{submission}/{documentType}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'downloadDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
});

// Route::get('pusdatin/penilaian/penghargaan/template/{year}',  [PenilaianPenghargaan_Controller::class, 'downloadTemplate']);

// Wilayah endpoints - Public access untuk dropdown filter
Route::get('/wilayah/provinces', function () {
    return response()->json([
        'data' => \App\Models\Region::where('type', 'provinsi')
            ->orderBy('nama_region')
            ->get(['id', 'nama_region'])
    ]);
});

Route::middleware(['auth:sanctum','role:admin'])->group(function () {
        // Dashboard endpoints
        Route::get('/admin/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'getStats']); // Alias untuk FE
        Route::get('/admin/dashboard/stats', [App\Http\Controllers\Admin\DashboardController::class, 'getStats']);
        Route::get('/admin/dashboard/activities', [App\Http\Controllers\Admin\DashboardController::class, 'getRecentActivities']);
        Route::get('/admin/users/{id}/detail', [App\Http\Controllers\Admin\DashboardController::class, 'getUserDetail']);
        
        // User management
        Route::patch('/admin/users/approve/{id}', [AdminController::class, 'approveUser']);
        Route::delete('/admin/users/reject/{id}', [AdminController::class, 'rejectUser']);
        Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/admin/users/pusdatin', [AdminController::class, 'createPusdatin']);
        Route::get('/admin/{role}/{status}',[AdminController::class,'showUser']);
        Route::get('/admin/track/{year?}/{pusdatin_id?}',[AdminController::class,'trackingHistoryPusdatin']);
        
        // Get list pusdatin untuk dropdown
        Route::get('/admin/pusdatin/approved', [AdminController::class, 'showUser'])->defaults('role', 'pusdatin')->defaults('status', 'approved');
    });

Route::middleware(['auth:sanctum', 'role:provinsi,kabupaten/kota', 'ensuresubmissions'])
->prefix('dinas/upload')
->group(function () {
    Route::post('/ringkasan-eksekutif', [UploadController::class, 'uploadRingkasanEksekutif'])
        ->middleware(['ensuredocument:ringkasanEksekutif', 'checkdeadline:submission']);
    Route::post('/laporan-utama', [UploadController::class, 'uploadLaporanUtama'])
        ->middleware(['ensuredocument:laporanUtama', 'checkdeadline:submission']);
    Route::post('/tabel-utama', [UploadController::class, 'uploadTabelUtama'])
        ->middleware(['ensuredocument:tabelUtama', 'checkdeadline:submission']);
    Route::post('/iklh', [UploadController::class, 'uploadIklh'])
        ->middleware(['ensuredocument:iklh', 'checkdeadline:submission']);
    
    Route::get('/status-dokumen', [UploadController::class, 'getStatusDokumen']);
    
    // Tabel Utama status endpoint
    Route::get('/tabel-utama/status', [UploadController::class, 'getTabelUtamaStatus']);
    Route::get('/tabel-utama/matra/{matra}', [UploadController::class, 'getTabelUtamaByMatraWithStatus'])
        ->where('matra', '.+'); // Match one or more characters
    Route::get('/tabel-utama/download/{kodeTabel}', [UploadController::class, 'downloadTabelUtama'])
        ->where('kodeTabel', '.+'); // Allow any character including spaces (URL encoded)
    
    // Preview & Download dokumen sendiri (termasuk draft)
    Route::get('/preview/{documentType}', [UploadController::class, 'previewDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
    Route::get('/download/{documentType}', [UploadController::class, 'downloadDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
    
    Route::patch('/finalize-submission', [UploadController::class, 'finalizeSubmission'])
        ->middleware('checkdeadline:submission');
    Route::patch('/finalize/{type}', [UploadController::class, 'finalizeOne'])
        ->where('type', 'ringkasanEksekutif|laporanUtama|tabelUtama|iklh')
        ->middleware(['ensuredocument', 'checkdeadline:submission']);
});


// Template Routes - Public access untuk dinas
Route::middleware(['auth:sanctum', 'role:provinsi,kabupaten/kota'])
->prefix('dinas/template')
->group(function () {
    Route::get('/matra', [UploadController::class, 'getMatraList']);
    Route::get('/matra/{matra}/tabel', [UploadController::class, 'getTabelByMatra']);
    // kodeTabel bisa mengandung spasi, akan di-encode otomatis di URL
    // Contoh: /download/Tabel%209 atau /download/Tabel+9
    Route::get('/download/{kodeTabel}', [UploadController::class, 'downloadTemplate']);
    Route::get('/download-all-zip', [UploadController::class, 'downloadAllTemplatesZip']);
    Route::get('/download-matra-zip/{matra}', [UploadController::class, 'downloadMatraZip'])
        ->where('matra', '.+'); // Allow any character including spaces and commas
});

// Pengumuman Routes - untuk dinas
Route::middleware(['auth:sanctum', 'role:provinsi,kabupaten/kota'])
->prefix('dinas/pengumuman')
->group(function () {
    Route::get('/timeline/{year?}', [App\Http\Controllers\Dinas\PengumumanController::class, 'timeline']);
    Route::get('/detail-slhd/{year?}', [App\Http\Controllers\Dinas\PengumumanController::class, 'getDetailPenilaianSLHD']);
    Route::get('/detail-penghargaan/{year?}', [App\Http\Controllers\Dinas\PengumumanController::class, 'getDetailPenilaianPenghargaan']);
    Route::get('/{year}/{tahap}', [App\Http\Controllers\Dinas\PengumumanController::class, 'show']);
});

// Dashboard Routes - untuk dinas (single endpoint untuk semua data dashboard)
Route::middleware(['auth:sanctum', 'role:provinsi,kabupaten/kota'])
->prefix('dinas/dashboard')
->group(function () {
    Route::get('/{year?}', [App\Http\Controllers\Dinas\DashboardController::class, 'index']);
});
        
Route::middleware(['auth:sanctum', 'role:pusdatin'])->prefix('pusdatin')->group(function () {
    // Dashboard endpoints
    Route::prefix('dashboard')->controller(App\Http\Controllers\Pusdatin\DashboardController::class)->group(function () {
        Route::get('/stats', 'getStats');
        Route::get('/tahapan', 'getTahapanProgress');
        Route::get('/notifications', 'getNotifications');
        Route::get('/activities', 'getRecentActivities');
    });
});


Route::middleware(['auth:sanctum', 'role:pusdatin'])->prefix('pusdatin/review')->group(function () {
    // List submissions untuk review dokumen upload
    Route::get('/{year?}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'index']);
    Route::get('/submission/{submission}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'show']);
    
    // Get detail dokumen per tipe (untuk lazy loading)
    Route::get('/submission/{submission}/ringkasan-eksekutif', [App\Http\Controllers\Pusdatin\ReviewController::class, 'showRingkasanEksekutif']);
    Route::get('/submission/{submission}/laporan-utama', [App\Http\Controllers\Pusdatin\ReviewController::class, 'showLaporanUtama']);
    Route::get('/submission/{submission}/tabel-utama', [App\Http\Controllers\Pusdatin\ReviewController::class, 'showTabelUtama']);
    Route::get('/submission/{submission}/tabel-utama/{tabelId}/download', [App\Http\Controllers\Pusdatin\ReviewController::class, 'downloadTabelUtama']);
    
    // Preview dokumen (inline PDF untuk iframe)
    Route::get('/submission/{submission}/preview/{documentType}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'previewDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
    
    // Download dokumen (force download)
    Route::get('/submission/{submission}/download/{documentType}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'downloadDocument'])
        ->where('documentType', 'ringkasan-eksekutif|laporan-utama');
    
    // Review dokumen
    Route::post('/submission/{submission}/{documentType}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'reviewDocument'])
        ->where('documentType', 'ringkasanEksekutif|laporanUtama|iklh');
    
    // IKLH Review - data IKLH per submission yang sudah finalized
    Route::get('/iklh/{year?}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'indexIKLH']);
    Route::post('/iklh/{submission}', [App\Http\Controllers\Pusdatin\ReviewController::class, 'reviewIKLH']);
});     
Route::get('/test-clean', function() {
    return response()->json(['status' => 'clean']);
});

Route::middleware(['auth:sanctum', 'role:pusdatin'])->prefix('pusdatin/penilaian')->group(function () {
    
    // Progress Stats untuk semua tahapan
    Route::get('/progress-stats', [App\Http\Controllers\Pusdatin\DashboardController::class, 'getProgressStats']);
    
    // Submissions dengan status kelayakan dokumen
    Route::get('/submissions', [App\Http\Controllers\Pusdatin\PenilaianSLHD_Controller::class, 'getSubmissionsStatus']);
    
    // Penilaian SLHD
    Route::prefix('slhd')->controller(PenilaianSLHD_Controller::class)->group(function () {
        Route::get('/template', 'downloadTemplate');
        Route::post('/upload/{year}', 'uploadPenilaianSLHD')
            ->middleware(['ensureevaluation:upload,penilaian_slhd', 'checkdeadline:penilaian_slhd']);
        Route::get('/{year}', 'getPenilaianSLHD');
        Route::get('/status/{penilaianSLHD}', 'status');
        Route::get('/parsed/{penilaianSLHD}', 'getAllParsedPenilaianSLHD');
        Route::patch('/finalize/{penilaianSLHD}', 'finalizePenilaianSLHD')
            ->middleware(['ensureevaluation:finalize,penilaian_slhd', 'checkdeadline:penilaian_slhd']);
        Route::patch('/unfinalize/{year}', 'unfinalized');
    });

    // Penilaian Penghargaan
    Route::prefix('penghargaan')->controller(App\Http\Controllers\Pusdatin\PenilaianPenghargaan_Controller::class)->group(function () {
        Route::get('/template/{year}', 'downloadTemplate');
        Route::post('/upload/{year}', 'uploadPenilaianPenghargaan')
            ->middleware(['ensureevaluation:upload,penilaian_penghargaan', 'checkdeadline:penilaian_penghargaan']);
        Route::get('/{year}', 'getPenilaianPenghargaan');
        Route::get('/status/{penilaianPenghargaan}', 'status');
        Route::get('/parsed/{penilaianPenghargaan}', 'getAllPenilaianPenghargaanParsed');
        Route::patch('/finalize/{penilaianPenghargaan}', 'finalizePenilaianPenghargaan')
            ->middleware(['ensureevaluation:finalize,penilaian_penghargaan', 'checkdeadline:penilaian_penghargaan']);
        Route::patch('/unfinalize/{year}', 'unfinalized');
    });

    // Validasi 1
    Route::prefix('validasi-1')->controller(App\Http\Controllers\Pusdatin\Validasi_1_Controller::class)->group(function () {
        Route::get('/{year}', 'index');
        Route::patch('/{year}/finalize', 'finalize')
            ->middleware(['ensureevaluation:finalize,validasi_1', 'checkdeadline:validasi_1']);
        Route::patch('/{year}/unfinalize', 'unfinalize');
    });

    // Validasi 2
    Route::prefix('validasi-2')->controller(App\Http\Controllers\Pusdatin\Validasi_2_Controller::class)->group(function () {
        Route::get('/{year}', 'index');
        Route::patch('/{validasi2Parsed}/checklist', 'updateCheklist');
        Route::post('/{year}/finalize', 'finalize')
            ->middleware(['ensureevaluation:finalize,validasi_2', 'checkdeadline:validasi_2']);
        Route::patch('/{year}/unfinalize', 'unfinalize');
        Route::get('/{year}/ranked', 'ranked');
        Route::post('/{year}/create-wawancara', 'createWawancara'); // Create wawancara dengan top N
    });

    // Wawancara (Tahap Terakhir)
    Route::prefix('wawancara')->controller(App\Http\Controllers\Pusdatin\WawancaraController::class)->group(function () {
        Route::get('/{year}', 'index'); // List wawancara dengan filter kategori optional
        Route::patch('/{wawancara}/nilai', 'updateNilai'); // Update nilai wawancara
        Route::patch('/{year}/finalize', 'finalize') // Finalize wawancara & hitung total skor final
            ->middleware('checkdeadline:wawancara');
        Route::patch('/{year}/unfinalize', 'unfinalize'); // Unfinalize wawancara
    });

    // Rekap Penilaian (Optional - untuk endpoint khusus rekap)
    Route::prefix('rekap')->controller(App\Http\Controllers\Pusdatin\RekapPenilaianController::class)->group(function () {
        Route::get('/{year}', 'index');
        Route::get('/{year}/dinas/{idDinas}', 'show');
    });

    // Deadline Management (Submission only)
    Route::prefix('deadline')->controller(App\Http\Controllers\Pusdatin\DeadlineController::class)->group(function () {
        Route::get('/{year?}', 'index'); // Get deadline submission
        Route::post('/set', 'setDeadline'); // Set/update deadline submission
        Route::delete('/{id}', 'deleteDeadline'); // Delete deadline
    });

    // Tahapan Penilaian Management
    Route::prefix('tahapan')->controller(App\Http\Controllers\Pusdatin\TahapanPenilaianController::class)->group(function () {
        Route::get('/{year}', 'index');
        Route::patch('/{year}/pengumuman', 'togglePengumuman');
        Route::patch('/{year}/tahap', 'updateTahap');
    });

});