<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\TahapanPenilaianStatus;
use App\Models\Deadline;
use Illuminate\Http\Request;
use App\Models\Pusdatin\RekapPenilaian;
use App\Models\Pusdatin\PenilaianSLHD;
use App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed;
use App\Models\Pusdatin\PenilaianPenghargaan;
use App\Models\Pusdatin\Parsed\PenilaianPenghargaan_Parsed;

class PengumumanController extends Controller
{
    /**
     * Get timeline progres penilaian (untuk progress bar)
     * Includes deadline info for efficiency
     */
    public function timeline(Request $request, $year = null)
    {
        $dinas = $request->user()->dinas;
        $year = $year ?? date('Y');
        
        // Get deadline submission untuk tahun ini
        $deadline = Deadline::byYear($year)
            ->byStage('submission')
            ->active()
            ->first();
        
        // Cek tahap aktif saat ini (auto-create jika belum ada, default submission)
        $tahapan = TahapanPenilaianStatus::firstOrCreate(
            ['year' => $year],
            [
                'tahap_aktif' => 'submission',
                'pengumuman_terbuka' => false,
                'tahap_mulai_at' => now(),
                'keterangan' => 'Tahap upload dokumen sedang berlangsung.'
            ]
        );
        
        // Ambil rekap penilaian dinas
        // $rekap = RekapPenilaian::where([
        //     'year' => $year,
        //     'id_dinas' => $dinas->id
        // ])->first();
        
        $tahapAktif = $tahapan->tahap_aktif;
        $urutanTahap = TahapanPenilaianStatus::URUTAN_TAHAP;
        $indexAktif = array_search($tahapAktif, $urutanTahap);
        
        $timeline = [];
        
        // Mapping nama tahap untuk display
        $namaTahap = [
            'submission' => 'Upload Dokumen',
            'penilaian_slhd' => 'Penilaian SLHD',
            'penilaian_penghargaan' => 'Penentuan Bobot Antar Penghargaan',
            'validasi_1' => 'Validasi 1',
            'validasi_2' => 'Validasi 2',
            'wawancara' => 'Wawancara'
        ];
        
        foreach ($urutanTahap as $index => $tahap) {
            $status = 'pending'; // Default: belum sampai
            $keterangan = 'Menunggu';
            
            if ($index < $indexAktif) {
                // Tahap sudah selesai
                $status = 'completed';
                $keterangan = 'Selesai';
            } elseif ($index === $indexAktif) {
                // Tahap sedang aktif
                $status = 'active';
                $keterangan = 'Sedang Berlangsung';
            }
            
            // Skip tahap 'selesai' untuk timeline (karena itu status akhir, bukan tahap visual)
            if ($tahap === 'selesai') {
                continue;
            }
            
            $timeline[] = [
                'tahap' => $tahap,
                'nama' => $namaTahap[$tahap] ?? $tahap,
                'status' => $status,
                'keterangan' => $keterangan
            ];
        }
        
        // Tambahkan tahap final jika sudah selesai semua
        if ($tahapAktif === 'selesai') {
            $timeline[] = [
                'tahap' => 'selesai',
                'nama' => 'Perhitungan NT Final',
                'status' => 'completed',
                'keterangan' => 'Selesai'
            ];
        } else {
            $timeline[] = [
                'tahap' => 'selesai',
                'nama' => 'Perhitungan NT Final',
                'status' => 'pending',
                'keterangan' => 'Menunggu'
            ];
        }
        
        return response()->json([
            'year' => $year,
            'tahap_aktif' => $tahapAktif,
            'pengumuman_terbuka' => $tahapan->pengumuman_terbuka,
            'keterangan' => $tahapan->keterangan,
            'deadline' => $deadline ? [
                'deadline_at' => $deadline->deadline_at->format('Y-m-d H:i:s'),
                'deadline_formatted' => $deadline->deadline_at->format('d F Y'),
                'is_passed' => $deadline->isPassed(),
                'catatan' => $deadline->catatan
            ] : null,
            'timeline' => $timeline
        ]);
    }
    
    /**
     * Lihat hasil pengumuman untuk tahap tertentu (dipanggil saat user klik tahap di timeline)
     */
    public function show(Request $request, $year, $tahap)
    {
        $dinas = $request->user()->dinas;
        
        // Cek status tahapan penilaian
        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();
        
        if (!$tahapan) {
            return response()->json([
                'message' => 'Belum ada penilaian untuk tahun ini'
            ], 404);
        }
        
        // Ambil rekap penilaian dinas
        $rekap = RekapPenilaian::where([
            'year' => $year,
            'id_dinas' => $dinas->id
        ])->first();
        
        // if (!$rekap) {
        //     return response()->json([
        //         'message' => 'Dinas Anda belum terdaftar dalam penilaian tahun ini'
        //     ], 404);
        // }
        
        // Tahap yang diminta dari parameter
        $tahapDiminta = $tahap;
        
        // Validasi tahap yang diminta
        $urutanTahap = TahapanPenilaianStatus::URUTAN_TAHAP;
        if (!in_array($tahapDiminta, $urutanTahap)) {
            return response()->json([
                'message' => 'Tahap tidak valid'
            ], 400);
        }
        
        // Cek apakah tahap sudah selesai (sudah ada pengumuman)
        $indexDiminta = array_search($tahapDiminta, $urutanTahap);
        $indexAktif = array_search($tahapan->tahap_aktif, $urutanTahap);
        
        // Tahap belum dimulai (masih di depan tahap aktif)
        if ($indexDiminta > $indexAktif) {
            return response()->json([
                'message' => 'Tahap ini belum dimulai',
                'tahap_diminta' => $tahapDiminta,
                'tahap_aktif' => $tahapan->tahap_aktif,
                'pengumuman_tersedia' => false
            ]);
        }
        
        // Cek pengumuman terbuka (berlaku untuk tahap aktif dan tahap yang sudah selesai)
        // Pengumuman bisa ditutup manual via toggle meskipun tahap sudah selesai
        if (!$tahapan->pengumuman_terbuka) {
            return response()->json([
                'message' => 'Pengumuman untuk tahap ini belum dibuka atau sedang ditutup sementara',
                'tahap_diminta' => $tahapDiminta,
                'tahap_aktif' => $tahapan->tahap_aktif,
                'pengumuman_tersedia' => false,
                'keterangan' => $tahapan->keterangan
            ]);
        }
        
        // Tahap sudah selesai ATAU tahap aktif dengan pengumuman terbuka
        // Generate dan return hasil
        $hasil = $this->generateHasilByTahap($tahapDiminta, $rekap);
        
        return response()->json([
            'tahap' => $tahapDiminta,
            'pengumuman_tersedia' => true,
            'hasil' => $hasil
        ]);
    }
    
    /**
     * Generate hasil pengumuman berdasarkan tahap
     */
    private function generateHasilByTahap($tahap, $rekap)
    {
        switch ($tahap) {
            case 'submission':
                return [
                    'tahap_diumumkan' => 'Upload Dokumen',
                    'status' => 'SELESAI',
                    'keterangan' => 'Dokumen submission Anda telah diterima dan sedang menunggu proses penilaian SLHD.'
                ];
                
            case 'penilaian_slhd':
                return [
                    'tahap_diumumkan' => 'Penilaian SLHD',
                    'nilai_slhd' => $rekap->nilai_slhd,
                    'status' => $rekap->lolos_slhd ? 'LOLOS' : 'TIDAK LOLOS',
                    'keterangan' => $rekap->lolos_slhd 
                        ? 'Selamat! Anda lolos tahap penilaian SLHD dan berhak mengikuti penilaian penghargaan.'
                        : 'Mohon maaf, nilai SLHD Anda belum memenuhi syarat untuk melanjutkan ke tahap berikutnya.'
                ];
                
            case 'penilaian_penghargaan':
                return [
                    'tahap_diumumkan' => 'Penilaian Penghargaan',
                    'nilai_slhd' => $rekap->nilai_slhd,
                    'nilai_penghargaan' => $rekap->nilai_penghargaan,
                    'status' => $rekap->masuk_penghargaan ? 'MASUK KATEGORI' : 'TIDAK MASUK',
                    'keterangan' => $rekap->masuk_penghargaan
                        ? 'Selamat! Anda masuk dalam kategori penilaian penghargaan.'
                        : 'Mohon maaf, nilai penghargaan Anda belum memenuhi syarat untuk melanjutkan ke validasi.'
                ];
                
            case 'validasi_1':
                return [
                    'tahap_diumumkan' => 'Validasi Tahap 1',
                    'nilai_penghargaan' => $rekap->nilai_penghargaan,
                    'nilai_iklh' => $rekap->nilai_iklh,
                    'total_skor' => $rekap->total_skor_validasi1,
                    'status' => $rekap->lolos_validasi1 ? 'LOLOS' : 'TIDAK LOLOS',
                    'keterangan' => $rekap->lolos_validasi1
                        ? 'Selamat! Anda lolos validasi tahap 1 dan akan diproses ke validasi tahap 2.'
                        : 'Mohon maaf, total skor Anda belum memenuhi syarat untuk melanjutkan ke validasi tahap 2.'
                ];
                
            case 'validasi_2':
                return [
                    'tahap_diumumkan' => 'Validasi Tahap 2',
                    'total_skor' => $rekap->total_skor_validasi1,
                    'kriteria_wtp' => $rekap->kriteria_wtp ? 'Memenuhi' : 'Tidak Memenuhi',
                    'kriteria_kasus_hukum' => $rekap->kriteria_kasus_hukum ? 'Memenuhi' : 'Tidak Memenuhi',
                    'status' => $rekap->lolos_validasi2 ? 'LOLOS' : 'TIDAK LOLOS',
                    'peringkat' => $rekap->peringkat,
                    'keterangan' => $rekap->lolos_validasi2
                        ? "Selamat! Anda lolos validasi tahap 2 dengan peringkat ke-{$rekap->peringkat} dan akan mengikuti tahap wawancara."
                        : 'Mohon maaf, Anda tidak lolos validasi tahap 2.'
                ];
                
            case 'wawancara':
                // Tahap wawancara sedang berlangsung, belum tentu semua dinas dapat nilai
                if ($rekap->nilai_wawancara === null) {
                    return [
                        'tahap_diumumkan' => 'Wawancara',
                        'status' => 'MENUNGGU',
                        'keterangan' => 'Anda lolos validasi tahap 2 dan masuk dalam daftar wawancara. Menunggu jadwal wawancara.'
                    ];
                }
                
                return [
                    'tahap_diumumkan' => 'Wawancara',
                    'nilai_wawancara' => $rekap->nilai_wawancara,
                    'status' => 'SELESAI WAWANCARA',
                    'keterangan' => 'Wawancara Anda telah selesai. Menunggu perhitungan NT Final.'
                ];
                
            case 'selesai':
                // Tahap selesai, semua sudah finalized
                if (!$rekap->lolos_wawancara || $rekap->total_skor_final === null) {
                    return [
                        'tahap_diumumkan' => 'Hasil Final',
                        'status' => 'TIDAK LOLOS',
                        'keterangan' => 'Mohon maaf, Anda tidak masuk dalam daftar wawancara atau tidak lolos tahap wawancara.'
                    ];
                }
                
                return [
                    'tahap_diumumkan' => 'Hasil Final',
                    'nilai_slhd' => $rekap->nilai_slhd,
                    'nilai_wawancara' => $rekap->nilai_wawancara,
                    'total_skor_final' => $rekap->total_skor_final,
                    'peringkat_final' => $rekap->peringkat_final,
                    'status' => 'LOLOS FINAL',
                    'keterangan' => "Selamat! Anda lolos semua tahap penilaian dengan peringkat final ke-{$rekap->peringkat_final}. Total skor final: {$rekap->total_skor_final} (90% SLHD + 10% Wawancara)."
                ];
                
            default:
                return null;
        }
    }

    /**
     * Get detail penilaian SLHD per BAB untuk dinas tertentu
     * Mengambil dari penilaian_slhd_parsed yang terhubung dengan penilaian_slhd status=finalized
     */
    public function getDetailPenilaianSLHD(Request $request, $year = null)
    {
        $dinas = $request->user()->dinas;
        $year = $year ?? date('Y');
        
        // Cek status tahapan penilaian
        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();
        
        if (!$tahapan) {
            return response()->json([
                'message' => 'Belum ada penilaian untuk tahun ini',
                'available' => false
            ], 404);
        }
        
        // Cek apakah tahap penilaian_slhd sudah selesai
        $urutanTahap = TahapanPenilaianStatus::URUTAN_TAHAP;
        $indexSlhd = array_search('penilaian_slhd', $urutanTahap);
        $indexAktif = array_search($tahapan->tahap_aktif, $urutanTahap);
        
        // Jika tahap penilaian_slhd belum selesai
        if ($indexSlhd >= $indexAktif) {
            return response()->json([
                'message' => 'Penilaian SLHD belum selesai',
                'available' => false,
                'tahap_aktif' => $tahapan->tahap_aktif
            ]);
        }
        
        // Cari penilaian SLHD yang finalized untuk tahun ini
        $penilaianSlhd = PenilaianSLHD::where('year', $year)
            ->where('status', 'finalized')
            ->first();
        
        if (!$penilaianSlhd) {
            return response()->json([
                'message' => 'Belum ada penilaian SLHD yang finalized untuk tahun ini',
                'available' => false
            ]);
        }
        
        // Cari data parsed untuk dinas ini
        $parsed = PenilaianSLHD_Parsed::where('penilaian_slhd_id', $penilaianSlhd->id)
            ->where('id_dinas', $dinas->id)
            ->first();
        
        if (!$parsed) {
            return response()->json([
                'message' => 'Data penilaian SLHD untuk dinas Anda tidak ditemukan',
                'available' => false
            ]);
        }
        
        // Bobot per BAB sesuai gambar
        // BAB I: Pendahuluan 10%
        // BAB II: Analisis Isu LH Daerah 50% (ini gabungan dari semua matra)
        // BAB III: Isu Prioritas Daerah 20%
        // BAB IV: Inovasi Daerah 15%
        // BAB V: Penutup 5%
        
        $bobotBab = [
            'bab_1' => 10,
            'bab_2' => 50,
            'bab_3' => 20,
            'bab_4' => 15,
            'bab_5' => 5
        ];
        
        // Hitung nilai BAB 2 dari komponen matra
        // BAB 2 terdiri dari: Lab, D3TLH, KLHS, dan 8 matra + Penetapan Isu Prioritas
        $komponenBab2 = [
            'Jumlah_Pemanfaatan_Pelayanan_Laboratorium',
            'Daya_Dukung_dan_Daya_Tampung_Lingkungan_Hidup',
            'Kajian_Lingkungan_Hidup_Strategis',
            'Keanekaragaman_Hayati',
            'Kualitas_Air',
            'Laut_Pesisir_dan_Pantai',
            'Kualitas_Udara',
            'Pengelolaan_Sampah_dan_Limbah',
            'Lahan_dan_Hutan',
            'Perubahan_Iklim',
            'Risiko_Bencana',
            'Penetapan_Isu_Prioritas'
        ];
        
        // Hitung rata-rata nilai BAB 2
        $nilaiKomponenBab2 = [];
        foreach ($komponenBab2 as $komponen) {
            $nilai = $parsed->$komponen;
            if ($nilai !== null) {
                $nilaiKomponenBab2[] = $nilai;
            }
        }
        $nilaiBab2 = count($nilaiKomponenBab2) > 0 
            ? round(array_sum($nilaiKomponenBab2) / count($nilaiKomponenBab2), 2)
            : 0;
        
        // Susun data per BAB
        $detailBab = [
            [
                'no' => 1,
                'komponen' => 'BAB I - Pendahuluan',
                'bobot' => $bobotBab['bab_1'],
                'nilai' => $parsed->Bab_1 ?? 0,
                'skor' => round((($parsed->Bab_1 ?? 0) * $bobotBab['bab_1']) / 100, 2)
            ],
            [
                'no' => 2,
                'komponen' => 'BAB II - Analisis Isu LH Daerah',
                'bobot' => $bobotBab['bab_2'],
                'nilai' => $nilaiBab2,
                'skor' => round(($nilaiBab2 * $bobotBab['bab_2']) / 100, 2)
            ],
            [
                'no' => 3,
                'komponen' => 'BAB III - Isu Prioritas Daerah',
                'bobot' => $bobotBab['bab_3'],
                'nilai' => $parsed->Bab_3 ?? 0,
                'skor' => round((($parsed->Bab_3 ?? 0) * $bobotBab['bab_3']) / 100, 2)
            ],
            [
                'no' => 4,
                'komponen' => 'BAB IV - Inovasi Daerah',
                'bobot' => $bobotBab['bab_4'],
                'nilai' => $parsed->Bab_4 ?? 0,
                'skor' => round((($parsed->Bab_4 ?? 0) * $bobotBab['bab_4']) / 100, 2)
            ],
            [
                'no' => 5,
                'komponen' => 'BAB V - Penutup',
                'bobot' => $bobotBab['bab_5'],
                'nilai' => $parsed->Bab_5 ?? 0,
                'skor' => round((($parsed->Bab_5 ?? 0) * $bobotBab['bab_5']) / 100, 2)
            ],
        ];
        
        // Total skor
        $totalSkor = array_sum(array_column($detailBab, 'skor'));
        
        // Detail komponen BAB 2 (matra)
        $detailMatra = [];
        $namaKomponenMap = [
            'Jumlah_Pemanfaatan_Pelayanan_Laboratorium' => 'Laboratorium',
            'Daya_Dukung_dan_Daya_Tampung_Lingkungan_Hidup' => 'D3TLH',
            'Kajian_Lingkungan_Hidup_Strategis' => 'KLHS',
            'Keanekaragaman_Hayati' => 'Keanekaragaman Hayati',
            'Kualitas_Air' => 'Kualitas Air',
            'Laut_Pesisir_dan_Pantai' => 'Laut, Pesisir, dan Pantai',
            'Kualitas_Udara' => 'Kualitas Udara',
            'Pengelolaan_Sampah_dan_Limbah' => 'Pengelolaan Sampah dan Limbah',
            'Lahan_dan_Hutan' => 'Lahan dan Hutan',
            'Perubahan_Iklim' => 'Perubahan Iklim',
            'Risiko_Bencana' => 'Risiko Bencana',
            'Penetapan_Isu_Prioritas' => 'Penetapan Isu Prioritas'
        ];
        
        foreach ($komponenBab2 as $komponen) {
            $detailMatra[] = [
                'komponen' => $namaKomponenMap[$komponen] ?? $komponen,
                'nilai' => $parsed->$komponen ?? 0
            ];
        }
        
        return response()->json([
            'available' => true,
            'year' => $year,
            'nama_dinas' => $dinas->name,
            'tanggal_finalized' => $penilaianSlhd->finalized_at,
            'detail_bab' => $detailBab,
            'total_skor' => round($totalSkor, 2),
            'detail_matra_bab2' => $detailMatra,
            'status' => $totalSkor >= 70 ? 'LULUS' : 'TIDAK LULUS',
            'keterangan' => $totalSkor >= 70 
                ? 'Selamat! Anda lulus penilaian SLHD.'
                : 'Mohon maaf, nilai SLHD Anda belum memenuhi passing grade (70).'
        ]);
    }

    /**
     * Get detail penilaian penghargaan per kategori untuk dinas tertentu
     * Mengambil dari penilaian_penghargaan_parsed yang terhubung dengan penilaian_penghargaan status=finalized
     */
    public function getDetailPenilaianPenghargaan(Request $request, $year = null)
    {
        $dinas = $request->user()->dinas;
        $year = $year ?? date('Y');
        
        // Cek status tahapan penilaian
        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();
        
        if (!$tahapan) {
            return response()->json([
                'message' => 'Belum ada penilaian untuk tahun ini',
                'available' => false
            ], 404);
        }
        
        // Cek apakah tahap penilaian_penghargaan sudah selesai
        $urutanTahap = TahapanPenilaianStatus::URUTAN_TAHAP;
        $indexPenghargaan = array_search('penilaian_penghargaan', $urutanTahap);
        $indexAktif = array_search($tahapan->tahap_aktif, $urutanTahap);
        
        // Jika tahap penilaian_penghargaan belum selesai
        if ($indexPenghargaan >= $indexAktif) {
            return response()->json([
                'message' => 'Penilaian Penghargaan belum selesai',
                'available' => false,
                'tahap_aktif' => $tahapan->tahap_aktif
            ]);
        }
        
        // Cari penilaian Penghargaan yang finalized untuk tahun ini
        $penilaianPenghargaan = PenilaianPenghargaan::where('year', $year)
            ->where('status', 'finalized')
            ->first();
        
        if (!$penilaianPenghargaan) {
            return response()->json([
                'message' => 'Belum ada penilaian Penghargaan yang finalized untuk tahun ini',
                'available' => false
            ]);
        }
        
        // Cari data parsed untuk dinas ini
        $parsed = PenilaianPenghargaan_Parsed::where('penilaian_penghargaan_id', $penilaianPenghargaan->id)
            ->where('id_dinas', $dinas->id)
            ->first();
        
        if (!$parsed) {
            return response()->json([
                'message' => 'Data penilaian Penghargaan untuk dinas Anda tidak ditemukan',
                'available' => false
            ]);
        }
        
        // Bobot per kategori penghargaan (sesuai ParsePenilaianPenghargaanJob.php)
        $bobot = [
            'Adipura' => 35,
            'Adiwiyata' => 15,
            'Proklim' => 19,
            'Proper' => 21,
            'Kalpataru' => 10
        ];
        
        // Susun data per kategori penghargaan
        $detailKategori = [
            [
                'no' => 1,
                'kategori' => 'Adipura',
                'bobot' => $bobot['Adipura'],
                'jumlah' => $parsed->Adipura_Jumlah_Wilayah ?? 0,
                'skor_max' => $parsed->Adipura_Skor_Max ?? 0,
                'skor' => $parsed->Adipura_Skor ?? 0,
                'persentase' => $parsed->Adipura_Skor_Max > 0 ? round(($parsed->Adipura_Skor / $parsed->Adipura_Skor_Max) * 100, 2) : 0,
                'nilai_tertimbang' => $parsed->Adipura_Skor_Max > 0 ? round(($parsed->Adipura_Skor / $parsed->Adipura_Skor_Max) * $bobot['Adipura'], 2) : 0
            ],
            [
                'no' => 2,
                'kategori' => 'Proper',
                'bobot' => $bobot['Proper'],
                'jumlah' => $parsed->Proper_Jumlah_Perusahaan ?? 0,
                'skor_max' => $parsed->Proper_Skor_Max ?? 0,
                'skor' => $parsed->Proper_Skor ?? 0,
                'persentase' => $parsed->Proper_Skor_Max > 0 ? round(($parsed->Proper_Skor / $parsed->Proper_Skor_Max) * 100, 2) : 0,
                'nilai_tertimbang' => $parsed->Proper_Skor_Max > 0 ? round(($parsed->Proper_Skor / $parsed->Proper_Skor_Max) * $bobot['Proper'], 2) : 0
            ],
            [
                'no' => 3,
                'kategori' => 'Proklim',
                'bobot' => $bobot['Proklim'],
                'jumlah' => $parsed->Proklim_Jumlah_Desa ?? 0,
                'skor_max' => $parsed->Proklim_Skor_Max ?? 0,
                'skor' => $parsed->Proklim_Skor ?? 0,
                'persentase' => $parsed->Proklim_Skor_Max > 0 ? round(($parsed->Proklim_Skor / $parsed->Proklim_Skor_Max) * 100, 2) : 0,
                'nilai_tertimbang' => $parsed->Proklim_Skor_Max > 0 ? round(($parsed->Proklim_Skor / $parsed->Proklim_Skor_Max) * $bobot['Proklim'], 2) : 0
            ],
            [
                'no' => 4,
                'kategori' => 'Adiwiyata',
                'bobot' => $bobot['Adiwiyata'],
                'jumlah' => $parsed->Adiwiyata_Jumlah_Sekolah ?? 0,
                'skor_max' => $parsed->Adiwiyata_Skor_Max ?? 0,
                'skor' => $parsed->Adiwiyata_Skor ?? 0,
                'persentase' => $parsed->Adiwiyata_Skor_Max > 0 ? round(($parsed->Adiwiyata_Skor / $parsed->Adiwiyata_Skor_Max) * 100, 2) : 0,
                'nilai_tertimbang' => $parsed->Adiwiyata_Skor_Max > 0 ? round(($parsed->Adiwiyata_Skor / $parsed->Adiwiyata_Skor_Max) * $bobot['Adiwiyata'], 2) : 0
            ],
            [
                'no' => 5,
                'kategori' => 'Kalpataru',
                'bobot' => $bobot['Kalpataru'],
                'jumlah' => $parsed->Kalpataru_Jumlah_Penerima ?? 0,
                'skor_max' => $parsed->Kalpataru_Skor_Max ?? 0,
                'skor' => $parsed->Kalpataru_Skor ?? 0,
                'persentase' => $parsed->Kalpataru_Skor_Max > 0 ? round(($parsed->Kalpataru_Skor / $parsed->Kalpataru_Skor_Max) * 100, 2) : 0,
                'nilai_tertimbang' => $parsed->Kalpataru_Skor_Max > 0 ? round(($parsed->Kalpataru_Skor / $parsed->Kalpataru_Skor_Max) * $bobot['Kalpataru'], 2) : 0
            ],
        ];
        
        // Total skor (dari Total_Skor yang sudah dihitung di job)
        $totalSkor = $parsed->Total_Skor ?? array_sum(array_column($detailKategori, 'nilai_tertimbang'));
        
        return response()->json([
            'available' => true,
            'year' => $year,
            'nama_dinas' => $dinas->nama_dinas,
            'tanggal_finalized' => $penilaianPenghargaan->finalized_at,
            'detail_kategori' => $detailKategori,
            'total_skor' => round($totalSkor, 2),
            'status' => $totalSkor >= 60 ? 'LULUS' : 'TIDAK LULUS',
            'keterangan' => $totalSkor >= 60 
                ? 'Selamat! Anda lulus penilaian Penghargaan.'
                : 'Mohon maaf, nilai Penghargaan Anda belum memenuhi passing grade (60).'
        ]);
    }
}