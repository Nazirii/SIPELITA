<?php

namespace App\Http\Controllers\Pusdatin;

use App\Http\Controllers\Controller;
use App\Models\Dinas;
use App\Models\Submission;
use App\Models\PusdatinLog;
use App\Models\TahapanPenilaianStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get statistik utama dashboard pusdatin
     */
    public function getStats(Request $request)
    {
        $year = $request->get('year', date('Y'));

        // 1. Total dinas terdaftar
        $totalDlh = Dinas::where('status','terdaftar')->count();

        // 2. SLHD Buku 1 - Upload dan Approved
        $buku1Upload = Submission::where('tahun', $year)
            ->whereHas('ringkasanEksekutif')
            ->count();
        $buku1Approved = Submission::where('tahun', $year)
            ->whereHas('ringkasanEksekutif', fn($sq) => $sq->where('status', 'approved'))
            ->count();

        // 3. SLHD Buku 2 - Upload dan Approved
        $buku2Upload = Submission::where('tahun', $year)
            ->whereHas('laporanUtama')
            ->count();
        $buku2Approved = Submission::where('tahun', $year)
            ->whereHas('laporanUtama', fn($sq) => $sq->where('status', 'approved'))
            ->count();

        // 4. IKLH - Upload dan Approved
        $iklhUpload = Submission::where('tahun', $year)
            ->whereHas('iklh')
            ->count();
        $iklhApproved = Submission::where('tahun', $year)
            ->whereHas('iklh', fn($sq) => $sq->where('status', 'approved'))
            ->count();

        // 5. Rata-rata Nilai SLHD (dari penilaian yang finalized)
        $slhd = \App\Models\Pusdatin\PenilaianSLHD::where(['year' => $year, 'status' => 'finalized'])->first();
        $avgNilaiSLHD = null;
        $statusPenilaian = 'Hasil Penilaian belum tersedia';
        
        if ($slhd && $slhd->is_finalized) {
            // Hitung rata-rata dari parsed data
            $avgNilaiSLHD = \App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed::where('penilaian_slhd_id', $slhd->id)
            ->avg('total_skor');
            
            if ($avgNilaiSLHD !== null) {
                $statusPenilaian = number_format($avgNilaiSLHD, 2);
            } else {
                $statusPenilaian = 'Data tidak tersedia';
            }
        }

        return response()->json([
            'total_dlh' => $totalDlh,
            'buku1_upload' => $buku1Upload,
            'buku1_approved' => $buku1Approved,
            'buku2_upload' => $buku2Upload,
            'buku2_approved' => $buku2Approved,
            'iklh_upload' => $iklhUpload,
            'iklh_approved' => $iklhApproved,
            'avg_nilai_slhd' => $statusPenilaian,
        ]);
    }

    /**
     * Get status tahapan penilaian untuk dashboard
     */
    public function getTahapanProgress(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();

        if (!$tahapan) {
            return response()->json([
                'tahap_aktif' => 'submission',
                'progress' => [],
            ]);
        }

        // Mapping tahapan ke progress
        $stages = [
            'submission' => [
                'name' => 'Penerimaan Data',
                'status' => 'SLHD Provinsi',
                'detail' => 'Pengumpulan dokumen SLHD',
                'completed' => in_array($tahapan->tahap_aktif, ['penilaian_slhd', 'penilaian_penghargaan', 'validasi_1', 'validasi_2', 'wawancara']),
            ],
            'penilaian_slhd' => [
                'name' => 'Tahap 1 (Penilaian SLHD)',
                'status' => 'SLHD Kab/Kota',
                'detail' => 'Penilaian dokumen SLHD',
                'completed' => in_array($tahapan->tahap_aktif, ['penilaian_penghargaan', 'validasi_1', 'validasi_2', 'wawancara']),
            ],
            'penilaian_penghargaan' => [
                'name' => 'Tahap 2 (Penghargaan)',
                'status' => 'Penilaian',
                'detail' => 'Penilaian dokumen penghargaan',
                'completed' => in_array($tahapan->tahap_aktif, ['validasi_1', 'validasi_2', 'wawancara']),
            ],
            'validasi_1' => [
                'name' => 'Tahap 3 (Validasi 1)',
                'status' => 'Validasi Awal',
                'detail' => 'Validasi dokumen tahap 1',
                'completed' => in_array($tahapan->tahap_aktif, ['validasi_2', 'wawancara']),
            ],
            'validasi_2' => [
                'name' => 'Tahap 4 (Validasi 2)',
                'status' => 'Validasi Lanjutan',
                'detail' => 'Validasi dokumen tahap 2',
                'completed' => in_array($tahapan->tahap_aktif, ['wawancara']),
            ],
            'wawancara' => [
                'name' => 'Tahap 5 (Wawancara)',
                'status' => 'Final',
                'detail' => 'Wawancara dan penilaian akhir',
                'completed' => false,
            ],
        ];

        $progress = [];
        foreach ($stages as $key => $stage) {
            $isActive = $tahapan->tahap_aktif === $key;
            $progress[] = [
                'stage' => $stage['name'],
                'status' => $stage['status'],
                'detail' => $stage['detail'],
                'is_completed' => $stage['completed'],
                'is_active' => $isActive,
                'progress' => $stage['completed'] ? 100 : ($isActive ? 50 : 0),
            ];
        }

        return response()->json([
            'tahap_aktif' => $tahapan->tahap_aktif,
            'progress' => $progress,
        ]);
    }

    /**
     * Get notifikasi dan pengumuman
     */
    public function getNotifications(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();

        $announcement = null;
        $notification = null;

        if ($tahapan && $tahapan->pengumuman_terbuka) {
            $announcement = $tahapan->keterangan ?? 'Pengumuman dibuka untuk tahun ' . $year;
        }

        // Cek notifikasi deadline terdekat
        $nearestDeadline = DB::table('deadlines')
            ->where('tahun', $year)
            ->where('tanggal_akhir', '>=', now())
            ->orderBy('tanggal_akhir', 'asc')
            ->first();

        if ($nearestDeadline) {
            $daysLeft = now()->diffInDays($nearestDeadline->tanggal_akhir);
            $notification = "Deadline {$nearestDeadline->stage} dalam {$daysLeft} hari";
        }

        return response()->json([
            'announcement' => $announcement,
            'notification' => $notification,
        ]);
    }

    /**
     * Get aktivitas terkini
     */
    public function getRecentActivities(Request $request)
    {
        $limit = $request->get('limit', 10);

        $activities = PusdatinLog::with(['dinas:id,nama_dinas'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'nama_dlh' => $log->dinas->nama_dinas ?? '-',
                    'status' => $this->mapStatus($log->activity_type),
                    'tanggal' => $log->created_at->format('d-m-Y'),
                    'aksi' => $log->activity_type,
                ];
            });

        return response()->json($activities);
    }

    /**
     * Map activity type ke status badge
     */
    private function mapStatus($activityType)
    {
        $statusMap = [
            'upload' => 'valid',
            'finalize' => 'valid',
            'approve' => 'valid',
            'reject' => 'menunggu validasi',
            'revision' => 'menunggu validasi',
        ];

        return $statusMap[$activityType] ?? 'valid';
    }

    /**
     * Get progress stats untuk semua tahapan penilaian
     */
    public function getProgressStats(Request $request)
    {
        $year = $request->get('year', date('Y'));

        // Get tahapan status
        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();

        // Get total DLH (Kabupaten/Kota)
        $totalDlh = Dinas::count();

        // SLHD Stats - ambil yang sudah finalized (record aktif/dipilih)
        $slhd = \App\Models\Pusdatin\PenilaianSLHD::where(['year' => $year, 'status' => 'finalized'])->first();
        $slhdStats = [
            'is_finalized' => $slhd ? $slhd->is_finalized : false,
            'finalized' => $slhd 
                ? \App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed::where('penilaian_slhd_id', $slhd->id)->count()
                : 0,
        ];

        // Penghargaan Stats - ambil yang sudah finalized (record aktif/dipilih)
        $penghargaan = \App\Models\Pusdatin\PenilaianPenghargaan::where(['year' => $year, 'status' => 'finalized'])->first();
        $penghargaanStats = [
            'is_finalized' => $penghargaan ? $penghargaan->is_finalized : false,
            'finalized' => $penghargaan
                ? \App\Models\Pusdatin\Parsed\PenilaianPenghargaan_Parsed::where('penilaian_penghargaan_id', $penghargaan->id)->count()
                : 0,
        ];

        // Validasi 1 Stats
        $validasi1 = \App\Models\Pusdatin\Validasi1::where('year', $year)->first();
        $validasi1Stats = [
            'is_finalized' => $validasi1 ? $validasi1->is_finalized : false,
            'processed' => $validasi1 
                ? \App\Models\Pusdatin\Parsed\Validasi1Parsed::where('validasi_1_id', $validasi1->id)->count()
                : 0,
            'lolos' => $validasi1 
                ? \App\Models\Pusdatin\Parsed\Validasi1Parsed::where('validasi_1_id', $validasi1->id)
                    ->where('status_result', 'lulus')
                    ->count()
                : 0,
        ];

        // Validasi 2 Stats
        $validasi2 = \App\Models\Pusdatin\Validasi2::where('year', $year)->first();
        $validasi2Stats = [
            'is_finalized' => $validasi2 ? $validasi2->is_finalized : false,
            'processed' => $validasi2
                ? \App\Models\Pusdatin\Parsed\Validasi2Parsed::where('validasi_2_id', $validasi2->id)->count()
                : 0,
            'checked' => $validasi2
                ? \App\Models\Pusdatin\Parsed\Validasi2Parsed::where('validasi_2_id', $validasi2->id)
                    ->where(function($q) {
                        $q->where('Kriteria_WTP', true)
                          ->orWhere('Kriteria_Kasus_Hukum', true);
                    })
                    ->count()
                : 0,
            'lolos' => $validasi2
                ? \App\Models\Pusdatin\Parsed\Validasi2Parsed::where('validasi_2_id', $validasi2->id)
                    ->where('status_validasi', 'lolos')
                    ->count()
                : 0,
        ];

        // Wawancara Stats
        $wawancaraStats = [
            'is_finalized' => false,
            'processed' => \App\Models\Pusdatin\Wawancara::where('year', $year)->count(),
            'with_nilai' => \App\Models\Pusdatin\Wawancara::where('year', $year)
                ->whereNotNull('nilai_wawancara')
                ->count(),
        ];

        // Check if wawancara is finalized (check from tahapan or wawancara table)
        if ($tahapan && $tahapan->tahap_aktif === 'selesai') {
            $wawancaraStats['is_finalized'] = true;
        }

        return response()->json([
            'data' => [
                'slhd' => $slhdStats,
                'penghargaan' => $penghargaanStats,
                'validasi1' => $validasi1Stats,
                'validasi2' => $validasi2Stats,
                'wawancara' => $wawancaraStats,
                'total_dlh' => $totalDlh,
                'tahap_aktif' => $tahapan ? $tahapan->tahap_aktif : 'submission',
            ]
        ]);
    }
}
