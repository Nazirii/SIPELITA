<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Submission;
use App\Models\TahapanPenilaianStatus;
use App\Models\Pusdatin\RekapPenilaian;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard stats untuk DLH
     * Single endpoint yang menggabungkan semua data dashboard
     */
    public function index(Request $request, $year = null)
    {
        $user = $request->user();
        $dinas = $user->dinas;
        $year = $year ?? date('Y');

        // Get submission untuk tahun ini
        $submission = Submission::where('id_dinas', $dinas->id)
            ->where('tahun', $year)
            ->with(['ringkasanEksekutif', 'laporanUtama', 'tabelUtama', 'iklh'])
            ->first();

        // Get deadline submission
        $deadline = Deadline::byYear($year)
            ->byStage('submission')
            ->active()
            ->first();

        // Get tahapan status
        $tahapan = TahapanPenilaianStatus::firstOrCreate(
            ['year' => $year],
            [
                'tahap_aktif' => 'submission',
                'pengumuman_terbuka' => false,
                'tahap_mulai_at' => now(),
                'keterangan' => 'Tahap upload dokumen sedang berlangsung.'
            ]
        );

        // Get rekap penilaian (jika ada)
        $rekap = RekapPenilaian::where([
            'year' => $year,
            'id_dinas' => $dinas->id
        ])->first();

        // Hitung stats dokumen
        $stats = $this->calculateDocumentStats($submission);

        // Build timeline
        $timeline = $this->buildTimeline($tahapan);

        return response()->json([
            'year' => $year,
            'dinas' => [
                'id' => $dinas->id,
                'nama' => $dinas->nama_dinas,
                'region' => $dinas->region?->nama_region,
                'type' => $dinas->region?->type,
                'has_pesisir' => $dinas->region?->has_pesisir ?? false,
            ],
            'stats' => $stats,
            'deadline' => $deadline ? [
                'deadline_at' => $deadline->deadline_at->format('Y-m-d H:i:s'),
                'deadline_formatted' => $deadline->deadline_at->translatedFormat('d F Y'),
                'is_passed' => $deadline->isPassed(),
                'days_remaining' => $deadline->isPassed() ? 0 : now()->diffInDays($deadline->deadline_at),
                'catatan' => $deadline->catatan
            ] : null,
            'tahapan' => [
                'tahap_aktif' => $tahapan->tahap_aktif,
                'pengumuman_terbuka' => $tahapan->pengumuman_terbuka,
                'keterangan' => $tahapan->keterangan,
            ],
            'timeline' => $timeline,
            'rekap' => $rekap ? [
                'nilai_slhd' => $rekap->nilai_slhd,
                'lolos_slhd' => $rekap->lolos_slhd,
                'nilai_penghargaan' => $rekap->nilai_penghargaan,
                'masuk_penghargaan' => $rekap->masuk_penghargaan,
                'nilai_iklh' => $rekap->nilai_iklh,
                'total_skor_validasi1' => $rekap->total_skor_validasi1,
                'lolos_validasi1' => $rekap->lolos_validasi1,
                'lolos_validasi2' => $rekap->lolos_validasi2,
                'kriteria_wtp' => $rekap->kriteria_wtp,
                'kriteria_kasus_hukum' => $rekap->kriteria_kasus_hukum,
                'nilai_wawancara' => $rekap->nilai_wawancara,
                'lolos_wawancara' => $rekap->lolos_wawancara,
                'total_skor_final' => $rekap->total_skor_final,
                'peringkat_final' => $rekap->peringkat_final,
                'peringkat' => $rekap->peringkat,
                'status_akhir' => $rekap->status_akhir,
            ] : null,
        ]);
    }

    /**
     * Calculate document upload stats
     */
    private function calculateDocumentStats(?Submission $submission): array
    {
        if (!$submission) {
            return [
                'total_dokumen' => 0,
                'total_required' => 4,
                'percentage' => 0,
                'dokumen' => [
                    ['nama' => 'Ringkasan Eksekutif', 'status' => 'belum', 'uploaded' => false],
                    ['nama' => 'Laporan Utama', 'status' => 'belum', 'uploaded' => false],
                    ['nama' => 'Tabel Utama', 'status' => 'belum', 'uploaded' => false, 'count' => 0],
                    ['nama' => 'IKLH', 'status' => 'belum', 'uploaded' => false],
                ]
            ];
        }

        $dokumen = [];
        $uploaded = 0;

        // Ringkasan Eksekutif
        $ringkasanExists = $submission->ringkasanEksekutif !== null;
        if ($ringkasanExists) $uploaded++;
        $dokumen[] = [
            'nama' => 'Ringkasan Eksekutif',
            'status' => $ringkasanExists ? $submission->ringkasanEksekutif->status : 'belum',
            'uploaded' => $ringkasanExists,
            'updated_at' => $ringkasanExists ? $submission->ringkasanEksekutif->updated_at->format('d-m-Y') : null,
        ];

        // Laporan Utama
        $laporanExists = $submission->laporanUtama !== null;
        if ($laporanExists) $uploaded++;
        $dokumen[] = [
            'nama' => 'Laporan Utama',
            'status' => $laporanExists ? $submission->laporanUtama->status : 'belum',
            'uploaded' => $laporanExists,
            'updated_at' => $laporanExists ? $submission->laporanUtama->updated_at->format('d-m-Y') : null,
        ];

        // Tabel Utama (show upload progress out of 80)
        $tabelCount = $submission->tabelUtama->count();
        $tabelFinalized = $submission->tabelUtama->where('status', 'finalized')->count();
        $totalRequired = 80; // Total tabel yang harus diupload
        if ($tabelCount > 0) $uploaded++;
        $dokumen[] = [
            'nama' => 'Tabel Utama',
            'status' => $tabelCount >= $totalRequired ? ($tabelFinalized === $tabelCount ? 'finalized' : 'draft') : 'belum',
            'uploaded' => $tabelCount > 0,
            'count' => $tabelCount,
            'total_required' => $totalRequired,
            'finalized_count' => $tabelFinalized,
        ];

        // IKLH
        $iklhExists = $submission->iklh !== null;
        if ($iklhExists) $uploaded++;
        $dokumen[] = [
            'nama' => 'IKLH',
            'status' => $iklhExists ? $submission->iklh->status : 'belum',
            'uploaded' => $iklhExists,
            'updated_at' => $iklhExists ? $submission->iklh->updated_at->format('d-m-Y') : null,
        ];

        return [
            'total_dokumen' => $uploaded,
            'total_required' => 4,
            'percentage' => round(($uploaded / 4) * 100),
            'submission_finalized' => $submission->is_finalized ?? false,
            'dokumen' => $dokumen,
        ];
    }

    /**
     * Build timeline array
     */
    private function buildTimeline(TahapanPenilaianStatus $tahapan): array
    {
        $tahapAktif = $tahapan->tahap_aktif;
        $urutanTahap = TahapanPenilaianStatus::URUTAN_TAHAP;
        $indexAktif = array_search($tahapAktif, $urutanTahap);

        $namaTahap = [
            'submission' => 'Upload Dokumen',
            'penilaian_slhd' => 'Penilaian SLHD',
            'penilaian_penghargaan' => 'Penentuan Bobot',
            'validasi_1' => 'Validasi 1',
            'validasi_2' => 'Validasi 2',
            'wawancara' => 'Wawancara',
            'selesai' => 'Hasil Final'
        ];

        $timeline = [];
        foreach ($urutanTahap as $index => $tahap) {
            $status = 'pending';
            $keterangan = 'Menunggu';

            if ($index < $indexAktif) {
                $status = 'completed';
                $keterangan = 'Selesai';
            } elseif ($index === $indexAktif) {
                $status = 'active';
                $keterangan = 'Sedang Berlangsung';
            }

            $timeline[] = [
                'tahap' => $tahap,
                'nama' => $namaTahap[$tahap] ?? $tahap,
                'status' => $status,
                'keterangan' => $keterangan
            ];
        }

        return $timeline;
    }
}
